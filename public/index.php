<?php
declare(strict_types=1);

/**
 * Front controller. All requests are routed through here (see .htaccess).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Router;
use App\Session;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;

Session::start();

$router = new Router();

// --- Public ---------------------------------------------------------
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// --- Root: send to dashboard or login ------------------------------
$router->get('/', static function (): void {
    redirect(Auth::check() && Auth::user() !== null ? '/dashboard' : '/login');
});

// --- Authenticated pages -------------------------------------------
$router->get('/dashboard',   [PageController::class, 'dashboard']);
$router->get('/eoffice',     [PageController::class, 'eoffice']);
$router->get('/ospyndocs',   [PageController::class, 'ospyndocs']);
$router->get('/bulk-upload', [PageController::class, 'bulkUpload']);
$router->get('/audit-log',   [PageController::class, 'auditLog']);

// --- Profile & password --------------------------------------------
$router->get('/profile',          [ProfileController::class, 'show']);
$router->post('/profile',         [ProfileController::class, 'update']);
$router->get('/change-password',  [AuthController::class, 'showChangePassword']);
$router->post('/change-password', [AuthController::class, 'changePassword']);

$router->dispatch();
