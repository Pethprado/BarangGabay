<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSession;
use GuzzleHttp\Client;

/**
 * "Continue with Google / Facebook" for residents.
 *
 * Standard OAuth 2.0 authorization-code flow with a session-bound `state`
 * (CSRF). The provider only vouches for an email address:
 *   - an existing resident account with that email is signed in;
 *   - staff/admin accounts must use their password (no social sign-in for
 *     back-office roles);
 *   - an unknown email is sent to registration with the name and email
 *     prefilled — the ID upload and staff verification still apply.
 *
 * Enabled per provider only when its keys are configured:
 *   GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
 *   FACEBOOK_APP_ID  / FACEBOOK_APP_SECRET
 */
class SocialAuthController
{
    /** Which providers are configured (used by the login/register views). */
    public static function enabled(): array
    {
        return [
            'google'   => env('GOOGLE_CLIENT_ID', '') !== '' && env('GOOGLE_CLIENT_SECRET', '') !== '',
            'facebook' => env('FACEBOOK_APP_ID', '') !== '' && env('FACEBOOK_APP_SECRET', '') !== '',
        ];
    }

    /** GET /auth/google */
    public function google(): void
    {
        $this->start('google', 'https://accounts.google.com/o/oauth2/v2/auth', [
            'client_id'     => (string) env('GOOGLE_CLIENT_ID', ''),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'prompt'        => 'select_account',
        ]);
    }

    /** GET /auth/google/callback */
    public function googleCallback(): void
    {
        $code = $this->checkCallback('google');
        try {
            $http  = new Client(['timeout' => 15]);
            $token = json_decode((string) $http->post('https://oauth2.googleapis.com/token', ['form_params' => [
                'code'          => $code,
                'client_id'     => (string) env('GOOGLE_CLIENT_ID', ''),
                'client_secret' => (string) env('GOOGLE_CLIENT_SECRET', ''),
                'redirect_uri'  => $this->callbackUrl('google'),
                'grant_type'    => 'authorization_code',
            ]])->getBody(), true);
            $info = json_decode((string) $http->get('https://openidconnect.googleapis.com/v1/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . ($token['access_token'] ?? '')],
            ])->getBody(), true);
            if (empty($info['email']) || empty($info['email_verified'])) {
                throw new \RuntimeException('Google did not return a verified email');
            }
            $this->finish('google', (string) $info['email'], (string) ($info['name'] ?? ''));
        } catch (\Throwable $e) {
            error_log('[SocialAuth google] ' . $e->getMessage());
            $this->fail();
        }
    }

    /** GET /auth/facebook */
    public function facebook(): void
    {
        $this->start('facebook', 'https://www.facebook.com/v19.0/dialog/oauth', [
            'client_id'     => (string) env('FACEBOOK_APP_ID', ''),
            'response_type' => 'code',
            'scope'         => 'email,public_profile',
        ]);
    }

    /** GET /auth/facebook/callback */
    public function facebookCallback(): void
    {
        $code = $this->checkCallback('facebook');
        try {
            $http  = new Client(['timeout' => 15]);
            $token = json_decode((string) $http->get('https://graph.facebook.com/v19.0/oauth/access_token', ['query' => [
                'client_id'     => (string) env('FACEBOOK_APP_ID', ''),
                'client_secret' => (string) env('FACEBOOK_APP_SECRET', ''),
                'redirect_uri'  => $this->callbackUrl('facebook'),
                'code'          => $code,
            ]])->getBody(), true);
            $info = json_decode((string) $http->get('https://graph.facebook.com/me', ['query' => [
                'fields'       => 'name,email',
                'access_token' => $token['access_token'] ?? '',
            ]])->getBody(), true);
            if (empty($info['email'])) {
                throw new \RuntimeException('Facebook did not share an email address');
            }
            $this->finish('facebook', (string) $info['email'], (string) ($info['name'] ?? ''));
        } catch (\Throwable $e) {
            error_log('[SocialAuth facebook] ' . $e->getMessage());
            $this->fail();
        }
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function start(string $provider, string $authUrl, array $params): void
    {
        if (empty(self::enabled()[$provider])) {
            flash('error', ucfirst($provider) . ' sign-in is not set up yet. Use your email and password.');
            redirect('/login');
        }
        $_SESSION['oauth_state'][$provider] = bin2hex(random_bytes(16));
        $params['redirect_uri'] = $this->callbackUrl($provider);
        $params['state']        = $_SESSION['oauth_state'][$provider];
        header('Location: ' . $authUrl . '?' . http_build_query($params));
        exit;
    }

    private function checkCallback(string $provider): string
    {
        $expected = (string) ($_SESSION['oauth_state'][$provider] ?? '');
        unset($_SESSION['oauth_state'][$provider]);
        $code = (string) ($_GET['code'] ?? '');
        if ($expected === '' || !hash_equals($expected, (string) ($_GET['state'] ?? '')) || $code === '') {
            flash('error', 'Sign-in was cancelled or expired. Please try again.');
            redirect('/login');
        }
        return $code;
    }

    private function finish(string $provider, string $email, string $name): void
    {
        $user = User::findByEmail($email);

        if ($user === null) {
            $_SESSION['oauth_prefill'] = ['email' => $email, 'full_name' => $name, 'provider' => $provider];
            flash('success', 'No BarangGabay account uses ' . $email . ' yet. Finish registering below — your ID still needs to be verified by the barangay.');
            redirect('/register');
        }
        if (($user['status'] ?? '') === 'suspended') {
            flash('error', t('login.err_suspended'));
            redirect('/login');
        }
        if (($user['role'] ?? '') !== 'resident') {
            AuditLog::record((int) $user['id'], 'auth.social_refused', ucfirst($provider) . ' sign-in refused for a back-office account');
            flash('error', 'Staff and admin accounts must sign in with their email and password.');
            redirect('/login');
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['status']    = $user['status'];
        $_SESSION['full_name'] = $user['full_name'];
        if (!empty($user['locale'])) {
            set_locale((string) $user['locale']);
        }
        UserSession::start((int) $user['id'], session_id());
        User::touchLastLogin((int) $user['id']);
        AuditLog::record((int) $user['id'], 'auth.login', 'User login with ' . ucfirst($provider));

        redirect(($user['status'] ?? '') === 'verified' ? '/' : '/pending');
    }

    private function fail(): void
    {
        flash('error', 'Sign-in with that provider failed. Please try again or use your email and password.');
        redirect('/login');
    }

    private function callbackUrl(string $provider): string
    {
        return rtrim(base_url(), '/') . '/auth/' . $provider . '/callback';
    }
}
