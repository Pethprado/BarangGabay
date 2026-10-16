<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ManoboHybridTranslator;
use App\Services\TranslationRetryRunner;

/**
 * Background upkeep the portal's own traffic drives (no cron needed).
 */
class MaintenanceController
{
    /** Seconds between translation sweeps per session (see public/index.php). */
    public const TRANSLATION_SWEEP_EVERY = 900;

    /** Seconds between hazard-check requests per session. */
    public const HAZARD_SWEEP_EVERY = 600;

    /**
     * GET /api/hazards/cron — for an outside scheduler (e.g. cron-job.org
     * every 15 minutes). Render's free plan sleeps when nobody visits, so
     * without this a typhoon alert at night would wait for the first visitor.
     * The ping also keeps the site awake.
     *
     * Safe to leave public: it runs the check at most once per 20 minutes
     * site-wide (HazardMonitor::runIfDue) and returns only public facts.
     */
    public function hazardCron(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(120);

        $summary = null;
        try {
            $summary = (new \App\Services\HazardMonitor())->runIfDue();
        } catch (\Throwable $e) {
            error_log('[hazard cron] ' . $e->getMessage());
        }
        $active = \App\Services\HazardMonitor::active();
        $last   = (int) \App\Models\Setting::get('hazard_last_check_at', 0);

        echo json_encode([
            'enabled'    => \App\Services\HazardMonitor::enabled(),
            'ran'        => $summary !== null,
            'summary'    => $summary,
            'last_check' => $last > 0 ? date('c', $last) : null,
            'next_check' => $last > 0 ? date('c', $last + \App\Services\HazardMonitor::CHECK_EVERY) : null,
            'active'     => array_map(static fn (array $a): array => [
                'source' => $a['source'], 'level' => $a['level'], 'title' => $a['title'], 'until' => $a['expires_at'],
            ], $active),
            'last_error' => (string) \App\Models\Setting::get('hazard_last_error', ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/hazards/sweep — run the automatic hazard check if it is due
     * (HazardMonitor: once per 20 minutes for the whole site). Called in the
     * background after a page has loaded, so nobody waits for it.
     */
    public function hazardSweep(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $last = (int) ($_SESSION['bg_hazard_sweep_at'] ?? 0);
        if (time() - $last < self::HAZARD_SWEEP_EVERY) {
            echo json_encode(['ran' => false]);
            return;
        }
        $_SESSION['bg_hazard_sweep_at'] = time();

        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(120);

        try {
            $summary = (new \App\Services\HazardMonitor())->runIfDue();
            echo json_encode(['ran' => $summary !== null, 'summary' => $summary]);
        } catch (\Throwable $e) {
            error_log('[hazard sweep] ' . $e->getMessage());
            echo json_encode(['ran' => true, 'error' => true]);
        }
    }

    /**
     * POST /api/translations/sweep — retry translations that failed or came
     * back partial (Tagalog left in the Manobo because the free Bisaya
     * service was throttled).
     *
     * Called by the page in the background after it has loaded
     * (shared/_background-sweep.php). It used to run INSIDE the page request,
     * where one post with 32 untranslated parts kept a resident's page
     * loading for minutes. Here it releases the session first, so the
     * resident can keep browsing while it works, and the Bisaya service gets
     * a fixed time budget.
     */
    public function translationSweep(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $last = (int) ($_SESSION['bg_translation_retry_at'] ?? 0);
        if (time() - $last < self::TRANSLATION_SWEEP_EVERY) {
            echo json_encode(['ran' => false]);
            return;
        }
        $_SESSION['bg_translation_retry_at'] = time();

        // Release the session lock: the resident's next page must not wait.
        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(90);

        $previous = ManoboHybridTranslator::$machineDeadline;
        ManoboHybridTranslator::$machineDeadline = microtime(true) + 25.0;
        try {
            // One post per sweep: the whole budget goes to finishing it.
            $summary = (new TranslationRetryRunner())->run(1);
            echo json_encode(['ran' => true, 'attempted' => $summary['attempted'], 'succeeded' => $summary['succeeded']]);
        } catch (\Throwable $e) {
            error_log('[translation retry sweep] ' . $e->getMessage());
            echo json_encode(['ran' => true, 'error' => true]);
        } finally {
            ManoboHybridTranslator::$machineDeadline = $previous;
        }
    }
}
