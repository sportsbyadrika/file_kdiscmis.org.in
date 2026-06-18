<?php
/**
 * Login screen. Expects: optional $old (array), $error (string).
 */
use App\Csrf;

$old = $old ?? [];
?>
<div class="login-wrap">
  <div class="login-card card shadow-sm">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <img src="<?= e(base_url('/assets/img/logo.svg')) ?>" alt="Logo" height="56" class="mb-3">
        <h1 class="h4 mb-1"><?= e(config('app.name', 'File Repository')) ?></h1>
        <p class="text-muted small mb-0">Sign in to continue</p>
      </div>

      <form action="<?= e(base_url('/login')) ?>" method="post" novalidate autocomplete="off">
        <?= Csrf::field() ?>

        <div class="mb-3">
          <label for="login" class="form-label">Username or Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="login" name="login"
                   value="<?= e($old['login'] ?? '') ?>" required autofocus>
          </div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" required>
            <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"
                    data-target="#password" aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
          </button>
        </div>
      </form>
    </div>
  </div>
  <p class="text-center text-muted small mt-3 mb-0">
    &copy; <?= date('Y') ?> <?= e(config('app.name', 'File Repository')) ?>
  </p>
</div>
