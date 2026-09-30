<?php
/**
 * Voice Training & Dataset Management Dashboard
 * BARANGGABAY Admin Interface
 */
$title = $title ?? 'Voice Training & AI Dataset Hub';
?>

<div class="container-fluid px-4 py-4" id="voice-training-app">
    <!-- ── 1. Page Header ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-bold px-2.5 py-1 rounded-full text-xs uppercase tracking-wider">
                    <i class="bi bi-cpu-fill me-1"></i>Language AI & Voice
                </span>
                <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold px-2.5 py-1 rounded-full text-xs">
                    <i class="bi bi-circle-fill me-1 text-[8px]"></i>Live Dataset Active
                </span>
            </div>
            <h2 class="h3 font-extrabold text-slate-800 dark:text-white mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-mic-fill text-amber-500"></i>
                Voice Training & AI Dataset Management
            </h2>
            <p class="text-slate-500 dark:text-slate-400 mb-0 text-sm">
                Train native voice pronunciations, manage Manobo word audio mappings, and configure language voice profiles for resident readers.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle btn-sm rounded-xl px-3 font-semibold" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download me-1"></i> Export Dataset
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-xl border-0 p-2">
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=csv')) ?>"><i class="bi bi-filetype-csv me-2 text-success fs-6"></i>Export as CSV</a></li>
                    <li><a class="dropdown-item rounded-lg py-1.5 text-xs font-medium" href="<?= e(route('admin/voice-training/export?format=json')) ?>"><i class="bi bi-filetype-json me-2 text-info fs-6"></i>Export as JSON</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-amber text-white bg-amber-500 hover:bg-amber-600 btn-sm rounded-xl shadow-sm font-bold px-3 py-1.5 d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addSampleModal">
                <i class="bi bi-plus-circle-fill fs-6"></i> + Add Voice Sample
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm mb-4 border-0 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($err = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-xl shadow-sm mb-4 border-0 bg-rose-500/10 text-rose-700 dark:text-rose-300" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── 2. Top Summary Metric Cards Bar ───────────────────────────────── -->
    <?php
        $totalAllSamples = (int)($stats['msm']['total'] ?? 0) + (int)($stats['fil']['total'] ?? 0) + (int)($stats['en']['total'] ?? 0);
        $approvedAllSamples = (int)($stats['msm']['approved'] ?? 0) + (int)($stats['fil']['approved'] ?? 0) + (int)($stats['en']['approved'] ?? 0);
    ?>
    <div class="row g-3 mb-4">
        <!-- Card 1: Active Profiles -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 p-3.5 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Active Profiles</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 d-flex align-items-center justify-content-center">
                        <i class="bi bi-soundwave fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="text-2xl font-black text-slate-800 dark:text-white">3 Profiles</span>
                    <span class="text-xs text-emerald-500 font-semibold"><i class="bi bi-check-circle-fill"></i> Ready</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-1 block">Manobo, Filipino, English</span>
            </div>
        </div>

        <!-- Card 2: Manobo Coverage -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 p-3.5 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Manobo Coverage</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 d-flex align-items-center justify-content-center">
                        <i class="bi bi-journal-check fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="text-2xl font-black text-slate-800 dark:text-white"><?= number_format((float)($coverage['coverage_percentage'] ?? 0), 1) ?>%</span>
                    <span class="text-xs text-slate-400 font-medium"><?= (int)($coverage['words_with_audio'] ?? 0) ?>/<?= (int)($coverage['total_dictionary_words'] ?? 0) ?> words</span>
                </div>
                <div class="progress mt-2 rounded-full" style="height: 6px;">
                    <div class="progress-bar bg-amber-500" role="progressbar" style="width: <?= (float)($coverage['coverage_percentage'] ?? 0) ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Card 3: Total Audio Samples -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 p-3.5 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Samples</span>
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 d-flex align-items-center justify-content-center">
                        <i class="bi bi-database-fill-check fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="text-2xl font-black text-slate-800 dark:text-white"><?= $totalAllSamples ?></span>
                    <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-bold"><?= $approvedAllSamples ?> Approved</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-1 block">Audio dataset files recorded</span>
            </div>
        </div>

        <!-- Card 4: Missing Pronunciations -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 p-3.5 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Missing Samples</span>
                    <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-500 d-flex align-items-center justify-content-center">
                        <i class="bi bi-mic-mute-fill fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="text-2xl font-black text-slate-800 dark:text-white"><?= (int)($coverage['missing_audio'] ?? 0) ?></span>
                    <span class="text-xs text-rose-500 font-semibold">Dictionary Words</span>
                </div>
                <span class="text-[11px] text-slate-400 mt-1 block">Awaiting native speaker recording</span>
            </div>
        </div>
    </div>

    <!-- ── 3. Language Profile Cards (Responsive 3-Column Desktop Grid) ──── -->
    <div class="row g-4 mb-4">
        <!-- Manobo Voice Card (Primary Focus) -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border-2 border-amber-500/50 shadow-md rounded-2xl bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent relative overflow-hidden dark:bg-slate-900">
                <!-- Top Accent Line -->
                <div class="h-1.5 w-100 bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600"></div>
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-amber-500 text-white font-extrabold px-2.5 py-1 rounded-md text-[10px] uppercase tracking-wider">PRIMARY LANGUAGE</span>
                                <span class="badge bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold px-2 py-0.5 rounded-full text-[10px]">
                                    <i class="bi bi-check-circle-fill me-1"></i>ACTIVE
                                </span>
                            </div>
                            <h4 class="font-extrabold text-slate-800 dark:text-white mb-0">Manobo (MN)</h4>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white d-flex align-items-center justify-content-center shadow-sm">
                            <i class="bi bi-mic-fill fs-5"></i>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-amber-500/20 mb-3">
                        <span class="text-[11px] text-slate-400 block font-semibold uppercase">Active Profile Name</span>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100 d-flex align-items-center gap-1.5 mt-0.5">
                            <i class="bi bi-person-bounding-box text-amber-500"></i>
                            <?= e($activeProfiles['msm']['profile_name'] ?? 'Manobo Community Voice') ?>
                        </span>
                        <span class="text-[11px] text-slate-400 block mt-1 font-mono">Provider: <?= e($activeProfiles['msm']['provider'] ?? 'dataset_hybrid') ?></span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="bg-white/80 dark:bg-slate-800/80 p-2 rounded-xl text-center border border-slate-200/50 dark:border-slate-700/50">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Samples</span>
                                <span class="text-base font-extrabold text-slate-800 dark:text-white"><?= (int)($stats['msm']['total'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white/80 dark:bg-slate-800/80 p-2 rounded-xl text-center border border-slate-200/50 dark:border-slate-700/50">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Approved</span>
                                <span class="text-base font-extrabold text-emerald-600 dark:text-emerald-400"><?= (int)($stats['msm']['approved'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white/80 dark:bg-slate-800/80 p-2 rounded-xl text-center border border-slate-200/50 dark:border-slate-700/50">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Pending</span>
                                <span class="text-base font-extrabold text-amber-600 dark:text-amber-400"><?= (int)($stats['msm']['pending'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Coverage bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1 text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-200">Manobo Voice Coverage</span>
                            <span class="font-extrabold text-amber-600 dark:text-amber-400"><?= number_format((float)($coverage['coverage_percentage'] ?? 0), 1) ?>%</span>
                        </div>
                        <div class="progress rounded-full bg-slate-200 dark:bg-slate-700" style="height: 8px;">
                            <div class="progress-bar bg-gradient-to-r from-amber-500 to-amber-600 rounded-full" role="progressbar" style="width: <?= (float)($coverage['coverage_percentage'] ?? 0) ?>%"></div>
                        </div>
                        <span class="text-[11px] text-slate-400 mt-1 block">
                            <?= (int)($coverage['words_with_audio'] ?? 0) ?> of <?= (int)($coverage['total_dictionary_words'] ?? 0) ?> dictionary words recorded
                        </span>
                    </div>

                    <div class="mt-auto d-flex gap-2">
                        <button class="btn btn-sm btn-amber text-white bg-amber-500 hover:bg-amber-600 flex-grow-1 rounded-xl font-bold py-2" data-bs-toggle="modal" data-bs-target="#profileModal_msm">
                            <i class="bi bi-gear-fill me-1"></i> Configure Profile
                        </button>
                        <button type="button" onclick="quickTestLanguage('msm')" class="btn btn-sm btn-outline-amber text-amber-600 border-amber-500 hover:bg-amber-500 hover:text-white rounded-xl font-bold px-3 py-2" title="Test Manobo Voice">
                            <i class="bi bi-volume-up-fill me-1"></i> Quick Test
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filipino Voice Card -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border border-slate-200 dark:border-slate-800 shadow-sm rounded-2xl bg-white dark:bg-slate-900 d-flex flex-column">
                <div class="h-1.5 w-100 bg-blue-500"></div>
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-blue-500 text-white font-extrabold px-2.5 py-1 rounded-md text-[10px] uppercase tracking-wider">NATIONAL</span>
                                <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold px-2 py-0.5 rounded-full text-[10px]">
                                    <i class="bi bi-check-circle-fill me-1"></i>ACTIVE
                                </span>
                            </div>
                            <h4 class="font-extrabold text-slate-800 dark:text-white mb-0">Filipino (FIL)</h4>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-blue-500/10 text-blue-500 d-flex align-items-center justify-content-center">
                            <i class="bi bi-translate fs-5"></i>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 mb-3">
                        <span class="text-[11px] text-slate-400 block font-semibold uppercase">Active Profile Name</span>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100 d-flex align-items-center gap-1.5 mt-0.5">
                            <i class="bi bi-person-check text-blue-500"></i>
                            <?= e($activeProfiles['fil']['profile_name'] ?? 'Filipino Default Voice') ?>
                        </span>
                        <span class="text-[11px] text-slate-400 block mt-1 font-mono">Provider: <?= e($activeProfiles['fil']['provider'] ?? 'system') ?></span>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl text-center border border-slate-100 dark:border-slate-800">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Total Samples</span>
                                <span class="text-lg font-extrabold text-slate-800 dark:text-white"><?= (int)($stats['fil']['total'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl text-center border border-slate-100 dark:border-slate-800">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Approved</span>
                                <span class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400"><?= (int)($stats['fil']['approved'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1 rounded-xl font-bold py-2" data-bs-toggle="modal" data-bs-target="#profileModal_fil">
                            <i class="bi bi-gear-fill me-1"></i> Configure Profile
                        </button>
                        <button type="button" onclick="quickTestLanguage('fil')" class="btn btn-sm btn-outline-blue text-blue-600 border-blue-500 hover:bg-blue-500 hover:text-white rounded-xl font-bold px-3 py-2" title="Test Filipino Voice">
                            <i class="bi bi-volume-up-fill me-1"></i> Quick Test
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- English Voice Card -->
        <div class="col-12 col-md-4">
            <div class="card h-100 border border-slate-200 dark:border-slate-800 shadow-sm rounded-2xl bg-white dark:bg-slate-900 d-flex flex-column">
                <div class="h-1.5 w-100 bg-slate-600"></div>
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-slate-600 text-white font-extrabold px-2.5 py-1 rounded-md text-[10px] uppercase tracking-wider">INTERNATIONAL</span>
                                <span class="badge bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold px-2 py-0.5 rounded-full text-[10px]">
                                    <i class="bi bi-check-circle-fill me-1"></i>ACTIVE
                                </span>
                            </div>
                            <h4 class="font-extrabold text-slate-800 dark:text-white mb-0">English (EN)</h4>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-slate-500/10 text-slate-500 d-flex align-items-center justify-content-center">
                            <i class="bi bi-globe fs-5"></i>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 mb-3">
                        <span class="text-[11px] text-slate-400 block font-semibold uppercase">Active Profile Name</span>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100 d-flex align-items-center gap-1.5 mt-0.5">
                            <i class="bi bi-person-check text-slate-500"></i>
                            <?= e($activeProfiles['en']['profile_name'] ?? 'English Default Voice') ?>
                        </span>
                        <span class="text-[11px] text-slate-400 block mt-1 font-mono">Provider: <?= e($activeProfiles['en']['provider'] ?? 'system') ?></span>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl text-center border border-slate-100 dark:border-slate-800">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Total Samples</span>
                                <span class="text-lg font-extrabold text-slate-800 dark:text-white"><?= (int)($stats['en']['total'] ?? 0) ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl text-center border border-slate-100 dark:border-slate-800">
                                <span class="text-[10px] text-slate-400 block font-bold uppercase">Approved</span>
                                <span class="text-lg font-extrabold text-emerald-600 dark:text-emerald-400"><?= (int)($stats['en']['approved'] ?? 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1 rounded-xl font-bold py-2" data-bs-toggle="modal" data-bs-target="#profileModal_en">
                            <i class="bi bi-gear-fill me-1"></i> Configure Profile
                        </button>
                        <button type="button" onclick="quickTestLanguage('en')" class="btn btn-sm btn-outline-slate text-slate-600 border-slate-400 hover:bg-slate-700 hover:text-white rounded-xl font-bold px-3 py-2" title="Test English Voice">
                            <i class="bi bi-volume-up-fill me-1"></i> Quick Test
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 4. Main 2-Column Split: Test Voice Player & Missing Queue ──────── -->
    <div class="row g-4 mb-4">
        <!-- Interactive Test Voice Player Card -->
        <div class="col-12 col-lg-6" id="testVoiceCard">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 h-100 d-flex flex-column">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title font-extrabold text-slate-800 dark:text-white mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-play-circle-fill text-amber-500 fs-5"></i>
                            Interactive Voice Tester
                        </h5>
                        <span class="badge bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold px-2.5 py-1 rounded-full text-xs">
                            Live Voice Preview
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 mb-0">Test dataset recordings and active profile synthesis live before resident release.</p>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    <form id="testVoiceForm" onsubmit="runVoiceTest(event)">
                        <div class="mb-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Select Language</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="test_language_radio" id="lang_msm" value="msm" checked onchange="document.getElementById('test_language').value='msm'">
                                <label class="btn btn-outline-amber text-xs font-bold py-1.5" for="lang_msm">Manobo (MN)</label>

                                <input type="radio" class="btn-check" name="test_language_radio" id="lang_fil" value="fil" onchange="document.getElementById('test_language').value='fil'">
                                <label class="btn btn-outline-amber text-xs font-bold py-1.5" for="lang_fil">Filipino (FIL)</label>

                                <input type="radio" class="btn-check" name="test_language_radio" id="lang_en" value="en" onchange="document.getElementById('test_language').value='en'">
                                <label class="btn btn-outline-amber text-xs font-bold py-1.5" for="lang_en">English (EN)</label>
                            </div>
                            <input type="hidden" id="test_language" value="msm">
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300 mb-0">Text to Speak</label>
                                <div class="d-flex gap-1">
                                    <button type="button" class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-0 cursor-pointer font-normal hover:bg-amber-500 hover:text-white transition-colors" onclick="document.getElementById('test_text').value='Maayong adlaw abaga'">+ Maayong adlaw</button>
                                    <button type="button" class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-0 cursor-pointer font-normal hover:bg-amber-500 hover:text-white transition-colors" onclick="document.getElementById('test_text').value='Barangay Notice'">+ Notice</button>
                                </div>
                            </div>
                            <input type="text" id="test_text" class="form-control form-control-sm rounded-xl py-2 text-sm" placeholder="e.g. Maayong adlaw / abaga / barangay announcement" required>
                            <div id="testTextValidation" class="text-xs text-rose-500 mt-1 d-none">Pakilagay ang text na gustong patugtugin.</div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-auto">
                            <button type="submit" id="btnTestVoice" class="btn btn-amber text-white bg-amber-500 hover:bg-amber-600 btn-sm rounded-xl font-bold px-4 py-2 d-flex align-items-center gap-2 shadow-sm">
                                <i class="bi bi-volume-up-fill fs-6"></i> Play Voice Preview
                            </button>
                            <span id="testVoiceSpinner" class="spinner-border spinner-border-sm text-amber-500 d-none" role="status"></span>
                        </div>
                    </form>

                    <!-- Interactive Voice Output Box -->
                    <div id="testResultBox" class="mt-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span id="testSourceBadge" class="badge bg-emerald-500 text-white text-xs font-bold px-2.5 py-1 rounded-md">Dataset Recording</span>
                            <span id="testProfileLabel" class="text-xs text-slate-500 font-mono">Profile</span>
                        </div>
                        <p id="testMessage" class="text-xs text-slate-600 dark:text-slate-300 mb-2 font-medium"></p>
                        <audio id="testAudioPlayer" controls class="w-100 rounded-lg d-none"></audio>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missing Manobo Pronunciations Queue -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 h-100 d-flex flex-column">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title font-extrabold text-slate-800 dark:text-white mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-mic-mute-fill text-rose-500 fs-5"></i>
                            Missing Manobo Pronunciations
                        </h5>
                        <p class="text-xs text-slate-400 mt-1 mb-0">Dictionary entries awaiting native speaker voice recordings.</p>
                    </div>
                    <span class="badge bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold px-2.5 py-1 rounded-full text-xs">
                        <?= count($missingPronunciations) ?> Pending Audio
                    </span>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    <?php if (empty($missingPronunciations)): ?>
                        <div class="text-center py-5 text-slate-400 text-xs my-auto">
                            <i class="bi bi-check-circle-fill text-emerald-500 fs-1 block mb-2"></i>
                            <span class="font-bold text-slate-700 dark:text-slate-200 block text-sm">100% Manobo Dictionary Audio Coverage!</span>
                            All dictionary entries currently have approved native pronunciations.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 230px; overflow-y: auto;">
                            <table class="table table-hover align-middle text-xs mb-0">
                                <thead>
                                    <tr class="text-slate-400 font-bold uppercase tracking-wider border-bottom text-[10px]">
                                        <th class="ps-2">Manobo Word</th>
                                        <th>Tagalog / English</th>
                                        <th class="text-end pe-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missingPronunciations as $m): ?>
                                        <tr>
                                            <td class="ps-2 font-bold text-slate-800 dark:text-slate-100"><?= e($m['manobo_word']) ?></td>
                                            <td class="text-slate-500 dark:text-slate-400"><?= e($m['tagalog_word'] ?: $m['english_word']) ?></td>
                                            <td class="text-end pe-2">
                                                <button type="button" class="btn btn-xs btn-amber text-white bg-amber-500 hover:bg-amber-600 rounded-lg font-bold px-2.5 py-1 shadow-sm" onclick="quickRecordForWord(<?= (int)$m['id'] ?>, '<?= e(addslashes($m['manobo_word'])) ?>')">
                                                    <i class="bi bi-mic-fill me-1"></i> Record
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-auto pt-3 border-top text-end">
                            <a href="<?= e(route('admin/voice-training?language=msm&status=PENDING')) ?>" class="text-xs font-bold text-amber-600 dark:text-amber-400 text-decoration-none">
                                View All Pending Manobo Words <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 5. Voice Dataset Explorer & Samples Table ─────────────────────── -->
    <div class="card border-0 shadow-sm rounded-2xl bg-white dark:bg-slate-900 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-5">
                    <h5 class="font-extrabold text-slate-800 dark:text-white mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-collection-play-fill text-amber-500"></i>
                        Voice Dataset Samples Management
                    </h5>
                    <p class="text-xs text-slate-400 mb-0">Search, review, play, and approve recorded voice samples across all languages.</p>
                </div>
                <div class="col-12 col-md-7">
                    <form method="get" action="<?= e(route('admin/voice-training')) ?>" class="row g-2">
                        <div class="col-12 col-sm-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-slate-50 dark:bg-slate-800 border-end-0 text-slate-400 rounded-start-xl">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control form-control-sm border-start-0 rounded-end-xl" placeholder="Search word, phrase, speaker..." value="<?= e($filters['search']) ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="language" class="form-select form-select-sm rounded-xl font-semibold" onchange="this.form.submit()">
                                <option value="">All Languages</option>
                                <option value="msm" <?= $filters['language'] === 'msm' ? 'selected' : '' ?>>Manobo (MN)</option>
                                <option value="fil" <?= $filters['language'] === 'fil' ? 'selected' : '' ?>>Filipino (FIL)</option>
                                <option value="en" <?= $filters['language'] === 'en' ? 'selected' : '' ?>>English (EN)</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3">
                            <select name="status" class="form-select form-select-sm rounded-xl font-semibold" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="APPROVED" <?= $filters['status'] === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                                <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                                <option value="DRAFT" <?= $filters['status'] === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                                <option value="REJECTED" <?= $filters['status'] === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-1">
                            <a href="<?= e(route('admin/voice-training')) ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-xl" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($samples)): ?>
                <div class="text-center py-5 text-slate-400">
                    <i class="bi bi-mic-mute text-slate-300 dark:text-slate-600 fs-1 block mb-2"></i>
                    <span class="font-bold text-slate-700 dark:text-slate-300 block">Walang Nahanap na Voice Samples</span>
                    <span class="text-xs">Mag-record o mag-upload ng bagong boses upang masimulan ang dataset.</span>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle text-sm mb-0">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr class="text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                                <th class="ps-4">Text / Word</th>
                                <th>Language</th>
                                <th>Speaker Label</th>
                                <th>Audio Preview</th>
                                <th>Dictionary Match</th>
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
                                                <i class="bi bi-info-circle me-1"></i><?= e(mb_strimwidth($s['notes'], 0, 32, '...')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['language'] === 'msm'): ?>
                                            <span class="badge bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20 font-bold px-2.5 py-1 rounded-md text-xs">Manobo</span>
                                        <?php elseif ($s['language'] === 'fil'): ?>
                                            <span class="badge bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20 font-bold px-2.5 py-1 rounded-md text-xs">Filipino</span>
                                        <?php else: ?>
                                            <span class="badge bg-slate-500/10 text-slate-700 dark:text-slate-300 border border-slate-500/20 font-bold px-2.5 py-1 rounded-md text-xs">English</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-slate-600 dark:text-slate-300 text-xs">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200"><?= e($s['speaker_label'] ?: 'Community') ?></span>
                                        <span class="text-slate-400 block text-[10px] uppercase font-mono"><?= e($s['voice_type']) ?></span>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <?php if (!empty($s['audio_url'])): ?>
                                            <audio controls preload="none" class="h-8 rounded-lg" style="max-width: 180px;">
                                                <source src="<?= e($s['audio_url']) ?>" type="<?= e($s['mime_type'] ?: 'audio/webm') ?>">
                                                Playback unavailable.
                                            </audio>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">No audio blob</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-xs">
                                        <?php if (!empty($s['manobo_word'])): ?>
                                            <span class="badge bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 font-semibold px-2 py-1 rounded-md">
                                                <i class="bi bi-book me-1"></i><?= e($s['manobo_word']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'APPROVED'): ?>
                                            <span class="badge bg-emerald-500 text-white font-bold px-2.5 py-1 rounded-md text-xs">Approved</span>
                                        <?php elseif ($s['status'] === 'PENDING'): ?>
                                            <span class="badge bg-amber-500 text-white font-bold px-2.5 py-1 rounded-md text-xs">Pending</span>
                                        <?php elseif ($s['status'] === 'REJECTED'): ?>
                                            <span class="badge bg-rose-500 text-white font-bold px-2.5 py-1 rounded-md text-xs">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-slate-400 text-white font-bold px-2.5 py-1 rounded-md text-xs">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-1.5">
                                            <?php if ($s['status'] !== 'APPROVED'): ?>
                                                <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-xs btn-emerald text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg font-bold px-2.5 py-1 shadow-sm" title="Approve Sample">
                                                        <i class="bi bi-check-lg me-1"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gustong idelete ang voice sample na ito?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-lg px-2 py-1" title="Delete Sample">
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

                <!-- Mobile Card List View -->
                <div class="d-block d-md-none p-3">
                    <div class="row g-3">
                        <?php foreach ($samples as $s): ?>
                            <div class="col-12">
                                <div class="p-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="font-bold text-slate-800 dark:text-white mb-0"><?= e($s['text']) ?></h6>
                                            <span class="text-xs text-slate-400 block"><?= e($s['speaker_label'] ?: 'Community') ?></span>
                                        </div>
                                        <?php if ($s['status'] === 'APPROVED'): ?>
                                            <span class="badge bg-emerald-500 text-white font-bold text-[10px]">APPROVED</span>
                                        <?php else: ?>
                                            <span class="badge bg-amber-500 text-white font-bold text-[10px]"><?= e($s['status']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($s['audio_url'])): ?>
                                        <div class="my-2">
                                            <audio controls preload="none" class="w-100 h-8 rounded-lg">
                                                <source src="<?= e($s['audio_url']) ?>" type="<?= e($s['mime_type'] ?: 'audio/webm') ?>">
                                            </audio>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                        <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold uppercase"><?= e($s['language']) ?></span>
                                        <div class="d-flex gap-1">
                                            <?php if ($s['status'] !== 'APPROVED'): ?>
                                                <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-xs btn-emerald text-white bg-emerald-600 rounded-md font-bold px-2 py-1">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="<?= e(route('admin/voice-training/update')) ?>" class="d-inline" onsubmit="return confirm('Sigurado ka bang gustong idelete ang voice sample na ito?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-xs btn-outline-danger rounded-md px-2 py-1"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="p-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 text-xs">
                        <span class="text-slate-400 font-medium">Showing <?= count($samples) ?> of <?= $pagination['total'] ?> voice dataset entries (Page <?= $pagination['page'] ?> of <?= $pagination['total_pages'] ?>)</span>
                        <div class="btn-group btn-group-sm">
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <a href="<?= e(route("admin/voice-training?page={$i}&language={$filters['language']}&status={$filters['status']}&search={$filters['search']}")) ?>" 
                                   class="btn btn-outline-secondary rounded-lg px-3 <?= $i === $pagination['page'] ? 'active font-bold bg-amber-500 text-white border-amber-500' : '' ?>">
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

<!-- ── 6. Modal: Add Voice Sample (Microphone Recording + File Upload) ────── -->
<div class="modal fade" id="addSampleModal" tabindex="-1" aria-labelledby="addSampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-2xl rounded-2xl dark:bg-slate-900">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-extrabold text-slate-800 dark:text-white d-flex align-items-center gap-2" id="addSampleModalLabel">
                    <i class="bi bi-mic-fill text-amber-500 fs-4"></i>Add Voice Sample
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addVoiceSampleForm" method="post" action="<?= e(route('admin/voice-training/samples')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Language <span class="text-danger">*</span></label>
                            <select name="language" id="sample_language" class="form-select form-select-sm rounded-xl font-semibold py-2" required>
                                <option value="msm" selected>Manobo (MN)</option>
                                <option value="fil">Filipino (FIL)</option>
                                <option value="en">English (EN)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Word or Phrase <span class="text-danger">*</span></label>
                            <input type="text" name="text" id="sample_text" class="form-control form-control-sm rounded-xl py-2" placeholder="e.g. abaga / Maayong adlaw sa inyong tanan" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Link to Dictionary Entry (Optional)</label>
                            <select name="dictionary_entry_id" id="sample_dictionary_entry_id" class="form-select form-select-sm rounded-xl font-semibold py-2">
                                <option value="">-- Select Manobo Word --</option>
                                <?php foreach ($dictionaryWords as $dw): ?>
                                    <option value="<?= (int)$dw['id'] ?>"><?= e($dw['manobo_word']) ?> (<?= e($dw['tagalog_word'] ?: $dw['english_word']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Speaker Name / Label</label>
                            <input type="text" name="speaker_label" class="form-control form-control-sm rounded-xl py-2" placeholder="e.g. Community Speaker">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Voice Type</label>
                            <select name="voice_type" class="form-select form-select-sm rounded-xl font-semibold py-2">
                                <option value="community" selected>Community Voice</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="neutral">Neutral</option>
                            </select>
                        </div>

                        <!-- Audio Source Tabs -->
                        <div class="col-12">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Audio Recording or File <span class="text-danger">*</span></label>
                            <ul class="nav nav-pills nav-fill bg-slate-100 dark:bg-slate-800 p-1 rounded-xl mb-3" id="audioTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active rounded-lg text-xs font-bold py-2" id="record-tab" data-bs-toggle="tab" data-bs-target="#record-panel" type="button" role="tab">
                                        <i class="bi bi-mic-fill me-1"></i> Option A: Record Microphone
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link rounded-lg text-xs font-bold py-2" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-panel" type="button" role="tab">
                                        <i class="bi bi-upload me-1"></i> Option B: Upload Audio File
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="audioTabContent">
                                <!-- Record Panel -->
                                <div class="tab-pane fade show active p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 text-center" id="record-panel" role="tabpanel">
                                    <div id="micControls">
                                        <button type="button" id="btnStartRec" onclick="startMicRecording()" class="btn btn-rose text-white bg-rose-500 hover:bg-rose-600 btn-md rounded-xl font-bold px-4 py-2 me-2 shadow-sm">
                                            <i class="bi bi-record-fill me-1"></i> Start Recording
                                        </button>
                                        <button type="button" id="btnStopRec" onclick="stopMicRecording()" class="btn btn-dark btn-md rounded-xl font-bold px-4 py-2 me-2 d-none">
                                            <i class="bi bi-stop-fill me-1"></i> Stop Recording
                                        </button>
                                        <span id="recTimer" class="font-mono text-sm text-rose-500 font-extrabold d-none">00:00</span>
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
                                <div class="tab-pane fade p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40" id="upload-panel" role="tabpanel">
                                    <input type="file" name="audio_file" id="audio_file" class="form-control form-control-sm rounded-xl py-2" accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm">
                                    <span class="text-[11px] text-slate-400 mt-1 block font-medium">Supported audio formats: MP3, WAV, M4A, OGG, WebM (Max 10 MB file size).</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Notes / Context</label>
                            <textarea name="notes" class="form-control form-control-sm rounded-xl" rows="2" placeholder="Optional notes regarding dialect pronunciation or cultural context..."></textarea>
                        </div>

                        <!-- Consent Checkbox -->
                        <div class="col-12">
                            <div class="form-check p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="consent_confirmed" value="1" id="consent_confirmed" checked required>
                                <label class="form-check-label text-xs font-medium text-slate-700 dark:text-slate-300" for="consent_confirmed">
                                    I confirm that the native speaker has authorized this voice recording for use in BarangGabay voice dataset.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <input type="hidden" name="status" id="sample_save_status" value="APPROVED">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-xl px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" onclick="document.getElementById('sample_save_status').value='DRAFT'" class="btn btn-light btn-sm rounded-xl px-3">Save Draft</button>
                    <button type="submit" onclick="document.getElementById('sample_save_status').value='APPROVED'" class="btn btn-amber text-white bg-amber-500 hover:bg-amber-600 btn-sm rounded-xl font-bold px-4 shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Save & Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── 7. Modals: Configure Voice Profiles (MSM, FIL, EN) ──────────────── -->
<?php foreach (['msm' => 'Manobo', 'fil' => 'Filipino', 'en' => 'English'] as $langKey => $langLabel): 
    $p = $activeProfiles[$langKey] ?? [];
?>
<div class="modal fade" id="profileModal_<?= $langKey ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-2xl rounded-2xl dark:bg-slate-900">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-extrabold text-slate-800 dark:text-white d-flex align-items-center gap-2">
                    <i class="bi bi-sliders text-amber-500"></i><?= $langLabel ?> Active Voice Profile
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
                        <label class="form-label text-xs font-bold">Profile Name</label>
                        <input type="text" name="profile_name" class="form-control form-control-sm rounded-xl py-2" value="<?= e($p['profile_name'] ?? "{$langLabel} Default Voice") ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold">Voice Provider Strategy</label>
                        <select name="provider" class="form-select form-select-sm rounded-xl font-semibold py-2">
                            <option value="dataset_hybrid" <?= ($p['provider'] ?? '') === 'dataset_hybrid' ? 'selected' : '' ?>>Dataset Recordings + SpeechSynthesis Hybrid</option>
                            <option value="system" <?= ($p['provider'] ?? '') === 'system' ? 'selected' : '' ?>>Browser Native SpeechSynthesis</option>
                            <option value="google_tts" <?= ($p['provider'] ?? '') === 'google_tts' ? 'selected' : '' ?>>Google Text-to-Speech API</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold">Provider Voice Identifier</label>
                        <input type="text" name="provider_voice_id" class="form-control form-control-sm rounded-xl py-2" value="<?= e($p['provider_voice_id'] ?? '') ?>" placeholder="e.g. mn-PH-Community / fil-PH-Standard">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-bold">Description</label>
                        <textarea name="description" class="form-control form-control-sm rounded-xl" rows="2"><?= e($p['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="consent_confirmed" value="1" id="consent_<?= $langKey ?>" checked>
                        <label class="form-check-label text-xs font-medium text-slate-700 dark:text-slate-300" for="consent_<?= $langKey ?>">
                            Authorized for BARANGGABAY Resident Reader use
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-xl px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber text-white bg-amber-500 hover:bg-amber-600 btn-sm rounded-xl font-bold px-4 shadow-sm">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ── 8. JavaScript Functions ────────────────────────────────────────── -->
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

function quickTestLanguage(lang) {
    const radioEl = document.getElementById('lang_' + lang);
    if (radioEl) {
        radioEl.checked = true;
    }
    document.getElementById('test_language').value = lang;

    const textEl = document.getElementById('test_text');
    if (textEl && !textEl.value) {
        textEl.value = (lang === 'msm') ? 'Maayong adlaw abaga' : (lang === 'fil' ? 'Magandang araw sa ating barangay' : 'Welcome to BarangGabay voice reader');
    }

    const testCard = document.getElementById('testVoiceCard');
    if (testCard) {
        testCard.scrollIntoView({ behavior: 'smooth' });
    }
}

function runVoiceTest(e) {
    e.preventDefault();
    const lang = document.getElementById('test_language').value;
    const textEl = document.getElementById('test_text');
    const text = textEl.value.trim();
    const validationEl = document.getElementById('testTextValidation');
    const spinner = document.getElementById('testVoiceSpinner');
    const resultBox = document.getElementById('testResultBox');
    const sourceBadge = document.getElementById('testSourceBadge');
    const profileLabel = document.getElementById('testProfileLabel');
    const messageEl = document.getElementById('testMessage');
    const audioPlayer = document.getElementById('testAudioPlayer');

    if (!text) {
        if (validationEl) validationEl.classList.remove('d-none');
        return;
    }
    if (validationEl) validationEl.classList.add('d-none');

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
                sourceBadge.className = 'badge bg-emerald-500 text-white text-xs font-bold px-2.5 py-1 rounded-md';
                sourceBadge.textContent = 'Approved Dataset Audio';
                profileLabel.textContent = `Speaker: ${data.speaker}`;
                messageEl.textContent = `Matching recording found for "${data.matched_text}". Playing audio sample.`;
                audioPlayer.src = data.audio_url;
                audioPlayer.classList.remove('d-none');
                audioPlayer.play();
            } else {
                sourceBadge.className = 'badge bg-blue-500 text-white text-xs font-bold px-2.5 py-1 rounded-md';
                sourceBadge.textContent = 'Voice Profile Synthesis';
                profileLabel.textContent = data.profile ? data.profile.profile_name : 'Active Profile';
                messageEl.textContent = data.message || 'Playing synthesized audio preview.';
                audioPlayer.classList.add('d-none');

                // Speak using browser SpeechSynthesis
                if ('speechSynthesis' in window) {
                    const u = new SpeechSynthesisUtterance(text);
                    u.lang = (lang === 'msm') ? 'ceb-PH' : (lang === 'fil' ? 'fil-PH' : 'en-US');
                    window.speechSynthesis.speak(u);
                }
            }
        } else {
            sourceBadge.className = 'badge bg-rose-500 text-white text-xs font-bold px-2.5 py-1 rounded-md';
            sourceBadge.textContent = 'Notice';
            messageEl.textContent = data.error || 'Failed to test voice.';
            audioPlayer.classList.add('d-none');
        }
    })
    .catch(err => {
        console.error('Test error:', err);
        spinner.classList.add('d-none');
        resultBox.classList.remove('d-none');
        sourceBadge.className = 'badge bg-rose-500 text-white text-xs font-bold px-2.5 py-1 rounded-md';
        sourceBadge.textContent = 'Error';
        messageEl.textContent = 'Network error testing voice preview.';
    });
}
</script>
