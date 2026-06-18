<?php
/**
 * Change Password screen. Expects: optional $errors (array).
 */
use App\Csrf;

$errors = $errors ?? [];
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><a href="<?= e(base_url('/dashboard')) ?>">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Change Password</li>
        </ol>
      </nav>

      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <h1 class="h5 mb-0"><i class="bi bi-key me-2"></i>Change Password</h1>
        </div>
        <div class="card-body">
          <form action="<?= e(base_url('/change-password')) ?>" method="post" novalidate autocomplete="off">
            <?= Csrf::field() ?>

            <div class="mb-3">
              <label for="current_password" class="form-label">Current Password</label>
              <input type="password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                     id="current_password" name="current_password" required>
              <?php if (isset($errors['current_password'])): ?>
                <div class="invalid-feedback"><?= e($errors['current_password']) ?></div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="new_password" class="form-label">New Password</label>
              <input type="password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                     id="new_password" name="new_password" required minlength="8">
              <div class="form-text">At least 8 characters.</div>
              <?php if (isset($errors['new_password'])): ?>
                <div class="invalid-feedback"><?= e($errors['new_password']) ?></div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="confirm_password" class="form-label">Confirm New Password</label>
              <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                     id="confirm_password" name="confirm_password" required>
              <?php if (isset($errors['confirm_password'])): ?>
                <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-2 justify-content-end">
              <a href="<?= e(base_url('/dashboard')) ?>" class="btn btn-outline-secondary">Cancel</a>
              <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
