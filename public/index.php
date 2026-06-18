<?php
declare(strict_types=1);

/**
 * Front controller / entry point.
 *
 * For Stage 1 (Foundation) this simply verifies the app is wired up and
 * routes the visitor onward. Authentication and the navbar shell arrive
 * in Stage 2, at which point unauthenticated users are sent to the login
 * page and authenticated users land on the Dashboard.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

// Stage 2 will replace this with: redirect to /login or /dashboard.
header('Content-Type: text/plain; charset=utf-8');
echo \App\Config::get('app.name', 'File Repository') . " — foundation OK.\n";
echo "Stage 1 complete. Auth & navbar shell arrive in Stage 2.\n";
