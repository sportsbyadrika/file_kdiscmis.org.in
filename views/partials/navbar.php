<?php
/**
 * Horizontal top navbar (replaces the original spec's sidebar).
 * Present on every authenticated page.
 *
 * Expects: $active (string) — active nav key.
 */
use App\Auth;
use App\Csrf;

$active = $active ?? '';
$user   = Auth::user() ?? ['full_name' => 'User', 'username' => 'user', 'email' => ''];
$displayName = $user['full_name'] !== '' ? $user['full_name'] : $user['username'];

$nav = [
    'dashboard'   => ['label' => 'Dashboard',        'href' => '/dashboard',   'icon' => 'bi-speedometer2'],
    'eoffice'     => ['label' => 'eOffice Files',     'href' => '/eoffice',     'icon' => 'bi-folder2-open'],
    'ospyndocs'   => ['label' => 'OspynDocs Files',   'href' => '/ospyndocs',   'icon' => 'bi-files'],
    'bulk-upload' => ['label' => 'Bulk Upload',       'href' => '/bulk-upload', 'icon' => 'bi-cloud-arrow-up'],
    'audit-log'   => ['label' => 'Audit Log',         'href' => '/audit-log',   'icon' => 'bi-clipboard-data'],
];

$initials = strtoupper(mb_substr($displayName, 0, 1));
?>
<nav class="navbar navbar-expand-md navbar-dark app-navbar sticky-top">
  <div class="container-fluid">
    <!-- Left: institution logo -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(base_url('/dashboard')) ?>">
      <img src="<?= e(base_url('/assets/img/logo.svg')) ?>" alt="Logo" height="32" class="app-logo">
      <span class="brand-text d-none d-sm-inline"><?= e(config('app.name', 'File Repository')) ?></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#mainNav" aria-controls="mainNav"
            aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <!-- Centre / left-of-centre: primary nav links -->
      <ul class="navbar-nav me-auto mb-2 mb-md-0">
        <?php foreach ($nav as $key => $item): ?>
          <li class="nav-item">
            <a class="nav-link <?= $active === $key ? 'active' : '' ?>"
               <?= $active === $key ? 'aria-current="page"' : '' ?>
               href="<?= e(base_url($item['href'])) ?>">
              <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Right: profile dropdown -->
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#"
             id="profileMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar-circle"><?= e($initials) ?></span>
            <span class="d-none d-md-inline"><?= e($displayName) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
            <li>
              <span class="dropdown-item-text small text-muted">
                Signed in as<br><strong><?= e($user['username']) ?></strong>
              </span>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item <?= $active === 'profile' ? 'active' : '' ?>" href="<?= e(base_url('/profile')) ?>">
                <i class="bi bi-person me-2"></i>My Profile
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= $active === 'change-password' ? 'active' : '' ?>" href="<?= e(base_url('/change-password')) ?>">
                <i class="bi bi-key me-2"></i>Change Password
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form action="<?= e(base_url('/logout')) ?>" method="post" class="px-1">
                <?= Csrf::field() ?>
                <button type="submit" class="dropdown-item text-danger">
                  <i class="bi bi-box-arrow-right me-2"></i>Logout
                </button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
