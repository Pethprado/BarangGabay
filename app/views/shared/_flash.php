<?php if ($message = flash('success')): ?>
    <div class="alert alert-success"
         style="padding:.75rem 1rem;border-radius:.5rem;background:#d1fae5;border:1px solid #a7f3d0;color:#065f46;margin-bottom:1rem;font-size:.9rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>
<?php if ($message = flash('warning')): ?>
    <div class="alert alert-warning"
         style="padding:.75rem 1rem;border-radius:.5rem;background:#fef9c3;border:1px solid #fde047;color:#854d0e;margin-bottom:1rem;font-size:.9rem;">
        ⚠️ <?= e($message) ?>
    </div>
<?php endif; ?>
<?php if (is_array($voice = flash('voice')) && !empty($voice['text'])): ?>
    <div class="alert" role="status"
         style="padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem;font-size:.9rem;display:flex;gap:.6rem;align-items:flex-start;flex-wrap:wrap;
                background:<?= !empty($voice['ok']) ? 'var(--status-success-bg, #d1fae5)' : 'var(--status-info-bg, #e0f2fe)' ?>;
                color:<?= !empty($voice['ok']) ? 'var(--status-success, #065f46)' : 'var(--status-info, #075985)' ?>;border:1px solid rgba(0,0,0,.08);">
        <i class="bi bi-mic-fill" aria-hidden="true" style="margin-top:.1rem;"></i>
        <span style="flex:1;min-width:14rem;"><?= e($voice['text']) ?></span>
        <?php if (!empty($voice['link'])): ?>
        <a href="<?= e($voice['link']) ?>" style="font-weight:700;white-space:nowrap;color:inherit;text-decoration:underline;">Record the missing words →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="alert alert-danger"
         style="padding:.75rem 1rem;border-radius:.5rem;background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;margin-bottom:1rem;font-size:.9rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

