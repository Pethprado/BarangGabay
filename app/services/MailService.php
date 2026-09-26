<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Thin wrapper around PHPMailer.
 *
 * All public send* methods build and dispatch a single branded email.
 * The generic send() is kept for ad-hoc messages from other controllers.
 */
class MailService
{
    // ── Public API ───────────────────────────────────────────────────

    /**
     * Send an email verification link to a newly registered resident.
     *
     * @param array  $user  Must contain 'email' and 'full_name'
     * @param string $token Raw base64-encoded verification token
     */
    public function sendVerificationEmail(array $user, string $token): bool
    {
        $name      = $user['full_name'] ?? 'Residente';
        $verifyUrl = app_url('verify-email?token=' . urlencode($token));

        $body = $this->htmlTemplate(
            "<h2 style=\"color:#1a6b3a;margin:0 0 16px;\">Kumusta, " . $this->esc($name) . "!</h2>
            <p style=\"color:#374151;line-height:1.7;margin:0 0 16px;\">
                Salamat sa pag-register sa <strong>BarangGabay</strong>. I-click ang button sa ibaba
                para ma-verify ang iyong email address at maproseso ang iyong aplikasyon.
            </p>
            <div style=\"text-align:center;margin:28px 0;\">
                <a href=\"" . $this->esc($verifyUrl) . "\"
                   style=\"background:#1a6b3a;color:#fff;padding:13px 32px;border-radius:8px;
                           font-weight:700;text-decoration:none;display:inline-block;font-size:.95rem;\">
                    I-verify ang Email Address
                </a>
            </div>
            <p style=\"color:#6b7280;font-size:.85rem;line-height:1.6;\">
                Pagkatapos ma-verify ang iyong email, susuriin ng barangay staff ang iyong valid ID.
                Makakatanggap ka ng notification kapag naaprubahan na ang iyong account.
            </p>
            <p style=\"color:#9ca3af;font-size:.78rem;\">
                Kung hindi ka nagrerehistro, maaari mong balewalain ang email na ito.
            </p>"
        );

        return $this->send(
            $user['email'],
            $name,
            'BarangGabay — I-verify ang iyong Email Address',
            $body
        );
    }

    /**
     * Send a welcome / account-approved email after admin verification.
     *
     * @param array $user  Must contain 'email' and 'full_name'
     */
    public function sendApprovalEmail(array $user): bool
    {
        $name     = $user['full_name'] ?? 'Residente';
        $loginUrl = app_url('login');

        $body = $this->htmlTemplate(
            "<div style=\"background:#d4edda;border:1px solid #c3e6cb;border-radius:8px;
                          padding:14px 18px;margin-bottom:24px;text-align:center;\">
                <p style=\"color:#155724;font-size:1rem;font-weight:700;margin:0;\">
                    ✓ Na-approve na ang iyong Account!
                </p>
            </div>
            <h2 style=\"color:#1a6b3a;margin:0 0 14px;\">Kamusta, " . $this->esc($name) . "!</h2>
            <p style=\"color:#374151;line-height:1.7;margin:0 0 16px;\">
                Maligayang balita! Ang iyong account sa <strong>BarangGabay</strong> ay na-verify na
                ng aming barangay admin team. Maaari ka na ngayong mag-login at gamitin ang lahat ng
                features ng sistema.
            </p>
            <div style=\"background:#f0faf4;border:1px solid #c3e6cb;border-radius:8px;
                         padding:16px 20px;margin:0 0 24px;\">
                <p style=\"color:#155724;font-weight:700;margin:0 0 8px;\">Maaari mo nang i-access ang:</p>
                <ul style=\"color:#4a5568;margin:0;padding-left:20px;line-height:1.9;\">
                    <li>Mga opisyal na anunsyo ng barangay</li>
                    <li>Mga darating na kaganapan at aktibidad</li>
                    <li>Mga ordinansa at patakaran (may AI-generated summary)</li>
                    <li>BarangGabay AI community assistant</li>
                </ul>
            </div>
            <div style=\"text-align:center;margin:28px 0;\">
                <a href=\"" . $this->esc($loginUrl) . "\"
                   style=\"background:#1a6b3a;color:#fff;padding:13px 36px;border-radius:8px;
                           font-weight:700;text-decoration:none;display:inline-block;font-size:.95rem;\">
                    Mag-login Ngayon &rarr;
                </a>
            </div>"
        );

        return $this->send(
            $user['email'],
            $name,
            'BarangGabay — Na-verify na ang iyong Account!',
            $body
        );
    }

    /**
     * Notify a resident about a new published announcement.
     *
     * @param array $user         Must contain 'email' and 'full_name'
     * @param array $announcement Must contain 'title', 'slug', 'category', 'urgency'
     */
    public function sendAnnouncementNotification(array $user, array $announcement): bool
    {
        $name  = $user['full_name'] ?? 'Residente';
        $title = $announcement['title'] ?? 'Bagong Anunsyo';
        $url   = app_url('announcements/' . ($announcement['slug'] ?? ''));

        $urgencyColors = [
            'urgent'    => ['#dc3545', 'URGENT'],
            'important' => ['#d97706', 'IMPORTANTE'],
            'normal'    => ['#6c757d', 'PANGKALAHATAN'],
        ];
        [$badgeColor, $badgeLabel] = $urgencyColors[$announcement['urgency'] ?? 'normal'];

        $body = $this->htmlTemplate(
            "<p style=\"margin:0 0 16px;\">
                <span style=\"background:{$badgeColor};color:#fff;padding:3px 10px;
                              border-radius:20px;font-size:.75rem;font-weight:700;
                              text-transform:uppercase;letter-spacing:.06em;\">
                    {$badgeLabel}
                </span>
            </p>
            <h2 style=\"color:#1a6b3a;margin:0 0 12px;\">" . $this->esc($title) . "</h2>
            <p style=\"color:#374151;line-height:1.7;margin:0 0 24px;\">
                Kumusta, " . $this->esc($name) . "! Mayroon kang bagong anunsyo mula sa
                Barangay Bayogo. Basahin ang buong anunsyo sa aming portal.
            </p>
            <div style=\"text-align:center;margin:28px 0;\">
                <a href=\"" . $this->esc($url) . "\"
                   style=\"background:#1a6b3a;color:#fff;padding:13px 32px;border-radius:8px;
                           font-weight:700;text-decoration:none;display:inline-block;font-size:.95rem;\">
                    Basahin ang Anunsyo
                </a>
            </div>"
        );

        return $this->send(
            $user['email'],
            $name,
            "BarangGabay — Bagong Anunsyo: {$title}",
            $body
        );
    }

    /**
     * Generic low-level send — used directly by controllers that build their own body.
     */
    public function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = (string) env('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) env('MAIL_USERNAME', '');
            $mail->Password   = (string) env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('MAIL_PORT', 587);
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                (string) env('MAIL_FROM_ADDRESS', 'no-reply@baranggabay.ph'),
                (string) env('MAIL_FROM_NAME', 'BarangGabay')
            );
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            return $mail->send();

        } catch (\Throwable $e) {
            error_log('[MailService] Failed to send to ' . $toEmail . ': ' . $e->getMessage());
            return false;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Wrap email content in the BarangGabay branded HTML shell.
     */
    private function htmlTemplate(string $content): string
    {
        $year        = (int) date('Y');
        $appName     = system_name();
        $appLocation = system_location();
        $appUrl      = app_url('');

        return <<<HTML
<!DOCTYPE html>
<html lang="fil">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$appName}</title>
</head>
<body style="margin:0;padding:0;background:#f4f9f5;font-family:'Segoe UI',system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#1a6b3a 0%,#124f2b 100%);
                        padding:28px 32px;border-radius:12px 12px 0 0;text-align:center;">
              <a href="{$appUrl}" style="text-decoration:none;">
                <span style="display:inline-block;width:44px;height:44px;background:#e8a020;
                             border-radius:12px;line-height:44px;font-size:1.4rem;
                             font-weight:800;color:#1c2b1e;margin-bottom:8px;">B</span>
                <p style="color:#fff;margin:0;font-size:1.3rem;font-weight:800;
                           letter-spacing:.01em;">{$appName}</p>
                <p style="color:rgba(255,255,255,.65);margin:3px 0 0;font-size:.78rem;
                           letter-spacing:.04em;">{$appLocation}</p>
              </a>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="background:#fff;padding:32px;border:1px solid #e4ece6;border-top:none;">
              {$content}
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fbf9;padding:16px 32px;border:1px solid #e4ece6;
                        border-top:none;border-radius:0 0 12px 12px;text-align:center;">
              <p style="color:#94a3b8;font-size:.72rem;margin:0;line-height:1.7;">
                Para sa mga katanungan, makipag-ugnayan sa Barangay Hall, {$appLocation}.<br>
                &copy; {$year} {$appName}. Lahat ng karapatan ay nakalaan.<br>
                <a href="{$appUrl}" style="color:#1a6b3a;text-decoration:none;">{$appUrl}</a>
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /** HTML-encode a value for safe interpolation in email HTML. */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}


