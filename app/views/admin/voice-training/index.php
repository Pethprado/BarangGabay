<?php
/**
 * Voice Training & Dataset Management Dashboard
 * BARANGGABAY Admin Interface
 */
$title = $title ?? 'Voice Training & Dataset Management';
?>

<div class="container-fluid px-4 py-4" id="voice-training-app">
    <!-- ── Page Header ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="h3 font-extrabold text-slate-800 dark:text-white mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-mic-fill text-primary"></i>
                Voice Training & Dataset Management
            </h2>
            <p class="text-slate-500 dark:text-slate-400 mb-0 text-sm">
                Manage audio pronunciation samples, dictionary word mappings, and language voice profiles for resident audio reading.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle btn-sm rounded-lg" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download me-1"></i> Export Dataset
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="<?= e(route('admin/voice-training/export?format=csv')) ?>"><i class="bi bi-filetype-csv me-2 text-success"></i>Export as CSV</a></li>
                    <li><a class="dropdown-item" href="<?= e(route('admin/voice-training/export?format=json')) ?>"><i class="bi bi-filetype-json me-2 text-info"></i>Export as JSON</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-primary btn-sm rounded-lg shadow-sm font-semibold d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addSampleModal">
                <i class="bi bi-plus-circle-fill"></i> + Add Voice Sample
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($msg = get_flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($err = get_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-xl shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── Language Cards (Manobo Primary Focus) ───────────────────────── -->
    <div class="row g-3 mb-4">
        <!-- Manobo Voice Card (Primary Focus) -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-2 border-primary/40 shadow-sm rounded-2xl bg-gradient-to-br from-amber-500/5 via-primary/5 to-transparent relative overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-amber-500 text-white font-bold px-2.5 py-1 rounded-md text-xs uppercase tracking-wider">PRIMARY</span>
                            <h5 class="font-bold text-slate-800 dark:text-white mb-0">Manobo (MN)</h5>
                        </div>
                        <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold px-2.5 py-1 rounded-full text-xs">
                            <i class="bi bi-record-circle me-1"></i><?= e($activeProfiles['msm']['profile_name'] ?? 'Manobo Voice') ?>
                        </span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-white/80 dark:bg-slate-800/80 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700">
                                <span class="text-xs text-slate-400 block font-medium">Total Samples</span>
                                <span class="text-xl font-extrabold text-slate-700 dark:text-slate-100"><?= (int)($stats['msm']['total'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white/80 dark:bg-slate-800/80 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700">
                                <span class="text-xs text-slate-400 block font-medium">Approved</span>
                                <span class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= (int)($stats['msm']['approved'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Coverage bar -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1 text-xs">
                            <span class="font-medium text-slate-600 dark:text-slate-300">Dictionary Voice Coverage</span>
                            <span class="font-bold text-primary"><?= number_format((float)($coverage['coverage_pct'] ?? 0), 1) ?>%</span>
                        </div>
                        <div class="progress rounded-full" style="height: 8px;">
                            <div class="progress-bar bg-amber-500" role="progressbar" style="width: <?= (float)($coverage['coverage_pct'] ?? 0) ?>%"></div>
                        </div>
                        <div class="text-slate-400 text-[11px] mt-1">
                            <?= (int)($coverage['approved_count'] ?? 0) ?> / <?= (int)($coverage['total_words'] ?? 0) ?> Manobo dictionary words recorded
                        </div>
                    </div>

                    <button class="btn btn-sm btn-outline-primary w-100 rounded-xl font-semibold" data-bs-toggle="modal" data-bs-target="#profileModal_msm">
                        <i class="bi bi-gear-fill me-1"></i> Configure Manobo Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Filipino Voice Card -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-1 border-slate-200 dark:border-slate-800 shadow-sm rounded-2xl bg-white dark:bg-slate-900">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-blue-500 text-white font-bold px-2.5 py-1 rounded-md text-xs uppercase tracking-wider">FILIPINO</span>
                            <h5 class="font-bold text-slate-800 dark:text-white mb-0">Filipino (FIL)</h5>
                        </div>
                        <span class="badge bg-blue-500/10 text-blue-600 dark:text-blue-400 font-semibold px-2.5 py-1 rounded-full text-xs">
                            Active
                        </span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                <span class="text-xs text-slate-400 block font-medium">Total Samples</span>
                                <span class="text-xl font-extrabold text-slate-700 dark:text-slate-100"><?= (int)($stats['fil']['total'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                <span class="text-xs text-slate-400 block font-medium">Approved</span>
                                <span class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= (int)($stats['fil']['approved'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mb-3">
                        Active Profile: <strong class="text-slate-600 dark:text-slate-300"><?= e($activeProfiles['fil']['profile_name'] ?? 'Filipino Default') ?></strong>
                    </p>

                    <button class="btn btn-sm btn-outline-secondary w-100 rounded-xl font-semibold mt-auto" data-bs-toggle="modal" data-bs-target="#profileModal_fil">
                        <i class="bi bi-gear-fill me-1"></i> Configure Filipino Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- English Voice Card -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-1 border-slate-200 dark:border-slate-800 shadow-sm rounded-2xl bg-white dark:bg-slate-900">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-slate-600 text-white font-bold px-2.5 py-1 rounded-md text-xs uppercase tracking-wider">ENGLISH</span>
                            <h5 class="font-bold text-slate-800 dark:text-white mb-0">English (EN)</h5>
                        </div>
                        <span class="badge bg-slate-500/10 text-slate-600 dark:text-slate-400 font-semibold px-2.5 py-1 rounded-full text-xs">
                            Active
                        </span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                <span class="text-xs text-slate-400 block font-medium">Total Samples</span>
                                <span class="text-xl font-extrabold text-slate-700 dark:text-slate-100"><?= (int)($stats['en']['total'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                                <span class="text-xs text-slate-400 block font-medium">Approved</span>
                                <span class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= (int)($stats['en']['approved'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mb-3">
                        Active Profile: <strong class="text-slate-600 dark:text-slate-300"><?= e($activeProfiles['en']['profile_name'] ?? 'English Default') ?></strong>
                    </p>

                    <button class="btn btn-sm btn-outline-secondary w-100 rounded-xl font-semibold mt-auto" data-bs-toggle="modal" data-bs-target="#profileModal_en">
                        <i class="bi bi-gear-fill me-1"></i> Configure English Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Row: Interactive Voice Tester & Missing Pronunciations Queue ──── -->
    <div class="row g-4 mb-4">
        <!-- Live Voice Testing Tool -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="card-title font-extrabold text-slate-800 dark:text-white mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-play-circle-fill text-amber-500"></i>
                        Test Voice Player
                    </h5>
                    <p class="text-xs text-slate-400 mt-1 mb-0">Test dataset recordings and voice profile synthesis before releasing to resident reader.</p>
                </div>
                <div class="card-body p-4">
                    <form id="testVoiceForm" onsubmit="runVoiceTest(event)">
                        <div class="mb-3">
                            <label class="form-label text-xs font-semibold text-slate-600 dark:text-slate-300">Language</label>
                            <select id="test_language" class="form-select form-select-sm rounded-lg">
                                <option value="msm" selected>Manobo (msm)</option>
                                <option value="fil">Filipino (fil)</option>
                                <option value="en">English (en)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-xs font-semibold text-slate-600 dark:text-slate-300">Text to Speak</label>
                            <input type="text" id="test_text" class="form-control form-control-sm rounded-lg" placeholder="e.g. Maayong adlaw / abaga / barangay announcement" required>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" id="btnTestVoice" class="btn btn-amber text-white bg-amber-500 hover:bg-amber-600 btn-sm rounded-lg font-semibold d-flex align-items-center gap-1">
                                <i class="bi bi-volume-up-fill"></i> Play Voice Preview
                            </button>
                            <span id="testVoiceSpinner" class="spinner-border spinner-border-sm text-amber-500 d-none" role="status"></span>
                        </div>
                    </form>

                    <div id="testResultBox" class="mt-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span id="testSourceBadge" class="badge bg-emerald-500 text-white text-xs">Dataset Recording</span>
                            <span id="testProfileLabel" class="text-xs text-slate-400 font-mono">Profile</span>
                        </div>
                        <p id="testMessage" class="text-xs text-slate-600 dark:text-slate-300 mb-2"></p>
                        <audio id="testAudioPlayer" controls class="w-100 rounded-lg d-none"></audio>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missing Pronunciations Queue -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title font-extrabold text-slate-800 dark:text-white mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-mic-mute-fill text-rose-500"></i>
                            Missing Manobo Pronunciations
                        </h5>
                        <p class="text-xs text-slate-400 mt-1 mb-0">Dictionary words awaiting native audio recordings.</p>
                    </div>
                    <span class="badge bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold px-2.5 py-1 rounded-full text-xs">
                        <?= count($missingPronunciations) ?> Pending
                    </span>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($missingPronunciations)): ?>
                        <div class="text-center py-4 text-slate-400 text-xs">
                            <i class="bi bi-check-circle-fill text-emerald-500 fs-3 block mb-2"></i>
                            All Manobo dictionary words have approved recordings!
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-hover align-middle text-xs mb-0">
                                <thead>
                                    <tr class="text-slate-400 uppercase tracking-wider border-bottom">
                                        <th>Manobo</th>
                                        <th>Tagalog / English</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missingPronunciations as $m): ?>
                                        <tr>
                                            <td class="font-bold text-slate-800 dark:text-slate-200"><?= e($m['manobo_word']) ?></td>
                                            <td class="text-slate-500"><?= e($m['tagalog_word'] ?: $m['english_word']) ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-xs btn-outline-primary rounded-md font-semibold" onclick="quickRecordForWord(<?= (int)$m['id'] ?>, '<?= e(addslashes($m['manobo_word'])) ?>')">
                                                    <i class="bi bi-mic-fill me-1"></i> Record
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Voice Dataset Explorer & Filters ─────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <h5 class="font-extrabold text-slate-800 dark:text-white mb-0">Voice Dataset Samples</h5>
                    <p class="text-xs text-slate-400 mb-0">Search and manage existing recorded audio samples.</p>
                </div>
                <div class="col-12 col-md-8">
                    <form method="get" action="<?= e(route('admin/voice-training')) ?>" class="row g-2">
                        <div class="col-12 col-sm-4">
                            <input type="text" name="search" class="form-control form-control-sm rounded-lg" placeholder="Search word/phrase/speaker..." value="<?= e($filters['search']) ?>">
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="language" class="form-select form-select-sm rounded-lg" onchange="this.form.submit()">
                                <option value="">All Languages</option>
                                <option value="msm" <?= $filters['language'] === 'msm' ? 'selected' : '' ?>>Manobo</option>
                                <option value="fil" <?= $filters['language'] === 'fil' ? 'selected' : '' ?>>Filipino</option>
                                <option value="en" <?= $filters['language'] === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="status" class="form-select form-select-sm rounded-lg" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="APPROVED" <?= $filters['status'] === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                                <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                                <option value="DRAFT" <?= $filters['status'] === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                                <option value="REJECTED" <?= $filters['status'] === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-2">
                            <button type="submit" class="btn btn-sm btn-secondary w-100 rounded-lg">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($samples)): ?>
                <div class="text-center py-5 text-slate-400">
                    <i class="bi bi-mic text-slate-300 fs-1 block mb-2"></i>
                    Walang nahanap na voice samples base sa iyong mga pamantayan.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-sm mb-0">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr class="text-slate-400 text-xs uppercase tracking-wider">
                                <th class="ps-4">Text / Word</th>
                                <th>Language</th>
                                <th>Speaker Label</th>
                                <th>Audio Preview</th>
                                <th>Dict Link</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($samples as $s): ?>
                                <tr>
                                    <td class="ps-4 font-bold text-slate-800 dark:text-slate-100">
                                        <?= e($s['text']) ?>
                                        <?php if (!empty($s['notes'])): ?>
                                            <span class="d-block text-xs font-normal text-slate-400" title="<?= e($s['notes']) ?>">
                                                <i class="bi bi-info-circle me-1"></i><?= e(mb_strimwidth($s['notes'], 0, 30, '...')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['language'] === 'msm'): ?>
                                            <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-bold px-2 py-0.5 rounded">Manobo</span>
                                        <?php elseif ($s['language'] === 'fil'): ?>
                                            <span class="badge bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-bold px-2 py-0.5 rounded">Filipino</span>
                                        <?php else: ?>
                                            <span class="badge bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20 font-bold px-2 py-0.5 rounded">English</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-slate-600 dark:text-slate-300 text-xs">
                                        <?= e($s['speaker_label'] ?: 'Community') ?>
                                        <span class="text-slate-400 block text-[10px]"><?= e($s['voice_type']) ?></span>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <?php if (!empty($s['audio_url'])): ?>
                                            <audio controls preload="none" class="h-8 rounded-lg" style="max-width: 180px;">
                                                <source src="<?= e($s['audio_url']) ?>" type="<?= e($s['mime_type'] ?: 'audio/webm') ?>">
                                                Your browser does not support audio playback.
                                            </audio>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">No audio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-xs">
                                        <?php if (!empty($s['manobo_word'])): ?>
                                            <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-medium">
                                                <i class="bi bi-book me-1"></i><?= e($s['manobo_word']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'APPROVED'): ?>
                                            <span class="badge bg-emerald-500 text-white font-bold px-2 py-1 rounded">Approved</span>
                                        <?php elseif ($s['status'] === 'PENDING'): ?>
                                            <span class="badge bg-amber-500 text-white font-bold px-2 py-1 rounded">Pending</span>
                                        <?php elseif ($s['status'] === 'REJECTED'): ?>
                                            <span class="badge bg-rose-500 text-white font-bold px-2 py-1 rounded">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-slate-400 text-white font-bold px-2 py-1 rounded">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($s['status'] !== 'APPROVED'): ?>
                                                <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-xs btn-outline-success rounded-md font-semibold" title="Approve Sample">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gustong idelete ang voice sample na ito?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-md" title="Delete Sample">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center text-xs">
                        <span class="text-slate-400">Total <?= $pagination['total'] ?> samples (Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?>)</span>
                        <div class="btn-group btn-group-sm">
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <a href="<?= e(route("admin/voice-training?page={$i}&language={$filters['language']}&status={$filters['status']}&search={$filters['search']}")) ?>" 
                                   class="btn btn-outline-secondary <?= $i === $pagination['page'] ? 'active font-bold' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Modal: Add Voice Sample (With Microphone Recording & Upload) ───────── -->
<div class="modal fade" id="addSampleModal" tabindex="-1" aria-labelledby="addSampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-2xl dark:bg-slate-900">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-extrabold text-slate-800 dark:text-white" id="addSampleModalLabel">
                    <i class="bi bi-mic-fill text-primary me-2"></i>Add Voice Sample
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addVoiceSampleForm" method="post" action="<?= e(route('admin/voice-training/samples')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Language <span class="text-danger">*</span></label>
                            <select name="language" id="sample_language" class="form-select form-select-sm rounded-lg" required>
                                <option value="msm" selected>Manobo (MN)</option>
                                <option value="fil">Filipino (FIL)</option>
                                <option value="en">English (EN)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Word or Phrase <span class="text-danger">*</span></label>
                            <input type="text" name="text" id="sample_text" class="form-control form-control-sm rounded-lg" placeholder="e.g. abaga / Maayong adlaw" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Link to Dictionary Entry (Optional)</label>
                            <select name="dictionary_entry_id" id="sample_dictionary_entry_id" class="form-select form-select-sm rounded-lg">
                                <option value="">-- Select Manobo Word --</option>
                                <?php foreach ($dictionaryWords as $dw): ?>
                                    <option value="<?= (int)$dw['id'] ?>"><?= e($dw['manobo_word']) ?> (<?= e($dw['tagalog_word'] ?: $dw['english_word']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Speaker Name / Label</label>
                            <input type="text" name="speaker_label" class="form-control form-control-sm rounded-lg" placeholder="e.g. Datu Elder">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Voice Type</label>
                            <select name="voice_type" class="form-select form-select-sm rounded-lg">
                                <option value="community" selected>Community Voice</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="neutral">Neutral</option>
                            </select>
                        </div>

                        <!-- Audio Source Tabs -->
                        <div class="col-12">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Audio Recording or File <span class="text-danger">*</span></label>
                            <ul class="nav nav-pills nav-fill bg-slate-100 dark:bg-slate-800 p-1 rounded-xl mb-3" id="audioTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active rounded-lg text-xs font-bold py-1.5" id="record-tab" data-bs-toggle="tab" data-bs-target="#record-panel" type="button" role="tab">
                                        <i class="bi bi-mic-fill me-1"></i> Option A: Record Microphone
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-lg text-xs font-bold py-1.5" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-panel" type="button" role="tab">
                                        <i class="bi bi-upload me-1"></i> Option B: Upload File
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="audioTabContent">
                                <!-- Record Panel -->
                                <div class="tab-pane fade show active p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 text-center" id="record-panel" role="tabpanel">
                                    <div id="micControls">
                                        <button type="button" id="btnStartRec" onclick="startMicRecording()" class="btn btn-rose text-white bg-rose-500 hover:bg-rose-600 btn-sm rounded-lg font-semibold px-3 py-1.5 me-2">
                                            <i class="bi bi-record-fill me-1"></i> Start Recording
                                        </button>
                                        <button type="button" id="btnStopRec" onclick="stopMicRecording()" class="btn btn-dark btn-sm rounded-lg font-semibold px-3 py-1.5 me-2 d-none">
                                            <i class="bi bi-stop-fill me-1"></i> Stop Recording
                                        </button>
                                        <span id="recTimer" class="font-mono text-xs text-rose-500 font-bold d-none">00:00</span>
                                    </div>

                                    <div id="micPreview" class="mt-3 d-none">
                                        <audio id="recAudioPlayer" controls class="w-100 h-9 rounded-lg mb-2"></audio>
                                        <button type="button" onclick="resetMicRecording()" class="btn btn-xs btn-outline-secondary rounded-lg">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Re-record
                                        </button>
                                    </div>
                                    <input type="hidden" name="audio_base64" id="audio_base64">
                                </div>

                                <!-- Upload Panel -->
                                <div class="tab-pane fade p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40" id="upload-panel" role="tabpanel">
                                    <input type="file" name="audio_file" id="audio_file" class="form-control form-control-sm rounded-lg" accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm">
                                    <span class="text-[11px] text-slate-400 mt-1 block">Supported formats: MP3, WAV, M4A, OGG, WebM (Max 10 MB).</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-xs font-semibold text-slate-700 dark:text-slate-300">Notes / Context</label>
                            <textarea name="notes" class="form-control form-control-sm rounded-lg" rows="2" placeholder="Optional notes regarding pronunciation or context..."></textarea>
                        </div>

                        <!-- Consent Checkbox -->
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="consent_confirmed" value="1" id="consent_confirmed" checked required>
                                <label class="form-check-label text-xs text-slate-600 dark:text-slate-400" for="consent_confirmed">
                                    I confirm that the speaker has authorized this voice recording for use in BarangGabay voice dataset.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <input type="hidden" name="status" id="sample_save_status" value="APPROVED">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-lg" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" onclick="document.getElementById('sample_save_status').value='DRAFT'" class="btn btn-light btn-sm rounded-lg">Save Draft</button>
                    <button type="submit" onclick="document.getElementById('sample_save_status').value='APPROVED'" class="btn btn-primary btn-sm rounded-lg font-semibold shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Save & Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modals: Configure Voice Profiles (MSM, FIL, EN) ──────────────── -->
<?php foreach (['msm' => 'Manobo', 'fil' => 'Filipino', 'en' => 'English'] as $langKey => $langLabel): 
    $p = $activeProfiles[$langKey] ?? [];
?>
<div class="modal fade" id="profileModal_<?= $langKey ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-2xl dark:bg-slate-900">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-extrabold text-slate-800 dark:text-white">
                    <i class="bi bi-sliders me-2 text-primary"></i><?= $langLabel ?> Active Voice Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= e(route('admin/voice-training/profile')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="language" value="<?= $langKey ?>">
                <input type="hidden" name="is_active" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold">Profile Name</label>
                        <input type="text" name="profile_name" class="form-control form-control-sm rounded-lg" value="<?= e($p['profile_name'] ?? "{$langLabel} Default Voice") ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold">Voice Provider Strategy</label>
                        <select name="provider" class="form-select form-select-sm rounded-lg">
                            <option value="dataset_hybrid" <?= ($p['provider'] ?? '') === 'dataset_hybrid' ? 'selected' : '' ?>>Dataset Recordings + SpeechSynthesis Hybrid</option>
                            <option value="system" <?= ($p['provider'] ?? '') === 'system' ? 'selected' : '' ?>>Browser Native SpeechSynthesis</option>
                            <option value="google_tts" <?= ($p['provider'] ?? '') === 'google_tts' ? 'selected' : '' ?>>Google Text-to-Speech API</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold">Provider Voice Identifier</label>
                        <input type="text" name="provider_voice_id" class="form-control form-control-sm rounded-lg" value="<?= e($p['provider_voice_id'] ?? '') ?>" placeholder="e.g. mn-PH-Community / fil-PH-Standard">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold">Description</label>
                        <textarea name="description" class="form-control form-control-sm rounded-lg" rows="2"><?= e($p['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="consent_confirmed" value="1" id="consent_<?= $langKey ?>" checked>
                        <label class="form-check-label text-xs text-slate-500" for="consent_<?= $langKey ?>">
                            Authorized for BARANGGABAY Resident Reader use
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-lg" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-lg font-semibold">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ── Client-side Audio Recorder & Testing JavaScript ───────────────── -->
<script>
let mediaRecorder = null;
let audioChunks = [];
let recTimerInterval = null;
let recSeconds = 0;

function startMicRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Ang iyong browser ay hindi sumusuporta sa microphone recording.');
        return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = event => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlob);
                
                const player = document.getElementById('recAudioPlayer');
                player.src = audioUrl;
                document.getElementById('micPreview').classList.remove('d-none');

                // Convert blob to base64
                const reader = new FileReader();
                reader.readAsDataURL(audioBlob);
                reader.onloadend = () => {
                    document.getElementById('audio_base64').value = reader.result;
                };

                // Stop tracks
                stream.getTracks().forEach(track => track.stop());
            };

            mediaRecorder.start();
            document.getElementById('btnStartRec').classList.add('d-none');
            document.getElementById('btnStopRec').classList.remove('d-none');
            document.getElementById('recTimer').classList.remove('d-none');

            recSeconds = 0;
            recTimerInterval = setInterval(() => {
                recSeconds++;
                const mins = String(Math.floor(recSeconds / 60)).padStart(2, '0');
                const secs = String(recSeconds % 60).padStart(2, '0');
                document.getElementById('recTimer').textContent = `${mins}:${secs}`;
            }, 1000);
        })
        .catch(err => {
            console.error('Microphone access denied:', err);
            alert('Hindi ma-access ang microphone. Pakipahintulutan ang browser permission.');
        });
}

function stopMicRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
        clearInterval(recTimerInterval);
        document.getElementById('btnStopRec').classList.add('d-none');
        document.getElementById('btnStartRec').classList.remove('d-none');
    }
}

function resetMicRecording() {
    audioChunks = [];
    document.getElementById('audio_base64').value = '';
    document.getElementById('micPreview').classList.add('d-none');
    document.getElementById('recTimer').classList.add('d-none');
    document.getElementById('btnStartRec').classList.remove('d-none');
    document.getElementById('btnStopRec').classList.add('d-none');
    clearInterval(recTimerInterval);
}

function quickRecordForWord(dictId, word) {
    document.getElementById('sample_language').value = 'msm';
    document.getElementById('sample_text').value = word;
    document.getElementById('sample_dictionary_entry_id').value = dictId;
    const modal = new bootstrap.Modal(document.getElementById('addSampleModal'));
    modal.show();
}

function runVoiceTest(e) {
    e.preventDefault();
    const lang = document.getElementById('test_language').value;
    const text = document.getElementById('test_text').value;
    const spinner = document.getElementById('testVoiceSpinner');
    const resultBox = document.getElementById('testResultBox');
    const sourceBadge = document.getElementById('testSourceBadge');
    const profileLabel = document.getElementById('testProfileLabel');
    const messageEl = document.getElementById('testMessage');
    const audioPlayer = document.getElementById('testAudioPlayer');

    spinner.classList.remove('d-none');
    resultBox.classList.add('d-none');

    const formData = new FormData();
    formData.append('language', lang);
    formData.append('text', text);

    fetch('<?= e(route('admin/voice-training/test')) ?>', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '<?= csrf_token() ?>',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        spinner.classList.add('d-none');
        resultBox.classList.remove('d-none');

        if (data.success) {
            if (data.source === 'dataset_recording' && data.audio_url) {
                sourceBadge.className = 'badge bg-emerald-500 text-white text-xs';
                sourceBadge.textContent = 'Approved Dataset Audio';
                profileLabel.textContent = `Speaker: ${data.speaker}`;
                messageEl.textContent = `Matching recording found for "${data.matched_text}". Playing audio sample.`;
                audioPlayer.src = data.audio_url;
                audioPlayer.classList.remove('d-none');
                audioPlayer.play();
            } else {
                sourceBadge.className = 'badge bg-blue-500 text-white text-xs';
                sourceBadge.textContent = 'Voice Profile Synthesis';
                profileLabel.textContent = data.profile ? data.profile.profile_name : 'Active Profile';
                messageEl.textContent = data.message;
                audioPlayer.classList.add('d-none');

                // Speak using browser SpeechSynthesis
                if ('speechSynthesis' in window) {
                    const u = new SpeechSynthesisUtterance(text);
                    u.lang = (lang === 'msm') ? 'ceb-PH' : (lang === 'fil' ? 'fil-PH' : 'en-US');
                    window.speechSynthesis.speak(u);
                }
            }
        } else {
            sourceBadge.className = 'badge bg-rose-500 text-white text-xs';
            sourceBadge.textContent = 'Error';
            messageEl.textContent = data.error || 'Failed to test voice.';
            audioPlayer.classList.add('d-none');
        }
    })
    .catch(err => {
        console.error('Test error:', err);
        spinner.classList.add('d-none');
        resultBox.classList.remove('d-none');
        sourceBadge.className = 'badge bg-rose-500 text-white text-xs';
        sourceBadge.textContent = 'Error';
        messageEl.textContent = 'Network or server error testing voice.';
    });
}
</script>
