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
<?php if ($message = flash('error')): ?>
    <div class="alert alert-danger"
         style="padding:.75rem 1rem;border-radius:.5rem;background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;margin-bottom:1rem;font-size:.9rem;">
        <?= e($message) ?>
    </div>
<?php endif; ?>

