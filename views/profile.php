<?php
/**
 * My Profile screen. Expects: $user (array), optional $errors (array).
 */
use App\Csrf;

$errors = $errors ?? [];
$user   = $user ?? [];
$created = !empty($user['created_at']) ? date('d-m-Y H:i', strtotime($user['created_at'])) : '—';
?>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-7">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
          <li class="breadcrumb-item"><a href="<?= e(base_url('/dashboard')) ?>">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">My Profile</li>
        </ol>
      </nav>

      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <h1 class="h5 mb-0"><i class="bi bi-person me-2"></i>My Profile</h1>
          <span class="badge text-bg-secondary text-uppercase"><?= e($user['role'] ?? 'admin') ?></span>
        </div>
        <div class="card-body">
          <form action="<?= e(base_url('/profile')) ?>" method="post" novalidate>
            <?= Csrf::field() ?>

            <div class="row mb-3">
              <div class="col-sm-6 mb-3 mb-sm-0">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?= e($user['username'] ?? '') ?>" readonly disabled>
                <div class="form-text">Username cannot be changed.</div>
              </div>
              <div class="col-sm-6">
                <label class="form-label">Member since</label>
                <input type="text" class="form-control" value="<?= e($created) ?>" readonly disabled>
              </div>
            </div>

            <div class="mb-3">
              <label for="full_name" class="form-label">Full Name</label>
              <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                     id="full_name" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required>
              <?php if (isset($errors['full_name'])): ?>
                <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                     id="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
              <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback"><?= e($errors['email']) ?></div>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-2 justify-content-end">
              <a href="<?= e(base_url('/change-password')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-key me-1"></i>Change Password
              </a>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
