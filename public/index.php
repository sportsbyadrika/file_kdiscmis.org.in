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
use App\Controllers\BulkController;
use App\Controllers\DashboardController;
use App\Controllers\FileListController;
use App\Controllers\PageController;
use App\Controllers\PdfController;
use App\Controllers\ProfileController;
use App\Controllers\WorkAreaController;

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
$router->get('/dashboard',       [DashboardController::class, 'index']);
$router->get('/dashboard/stats', [DashboardController::class, 'stats']);

// File List View — both apps share one controller (app resolved from path).
foreach (['eoffice', 'ospyndocs'] as $app) {
    $router->get("/{$app}",          [FileListController::class, 'index']);
    $router->get("/{$app}/data",     [FileListController::class, 'data']);
    $router->get("/{$app}/edit",     [FileListController::class, 'edit']);
    $router->post("/{$app}/update",  [FileListController::class, 'update']);
    $router->post("/{$app}/delete",  [FileListController::class, 'delete']);

    // File Work Area (Stage 5)
    $router->get("/{$app}/view",                 [WorkAreaController::class, 'show']);
    $router->post("/{$app}/note",                [WorkAreaController::class, 'saveNote']);
    $router->post("/{$app}/attachment/upload",   [WorkAreaController::class, 'uploadAttachment']);
    $router->post("/{$app}/attachment/delete",   [WorkAreaController::class, 'deleteAttachment']);
    $router->get("/{$app}/attachment/download",  [WorkAreaController::class, 'downloadAttachment']);
    $router->get("/{$app}/attachment/preview",   [WorkAreaController::class, 'previewAttachment']);
    $router->get("/{$app}/history.csv",          [WorkAreaController::class, 'historyCsv']);

    // PDF generation (Stage 6) — streamed in memory
    $router->get("/{$app}/pdf",                  [PdfController::class, 'generate']);
}

// Bulk Upload wizard (Stage 7)
$router->get('/bulk-upload',          [BulkController::class, 'index']);
$router->get('/bulk-upload/template', [BulkController::class, 'template']);
$router->post('/bulk-upload/validate', [BulkController::class, 'validate']);
$router->post('/bulk-upload/process',  [BulkController::class, 'process']);
$router->get('/bulk-upload/report',   [BulkController::class, 'report']);

$router->get('/audit-log',   [PageController::class, 'auditLog']);

// --- Profile & password --------------------------------------------
$router->get('/profile',          [ProfileController::class, 'show']);
$router->post('/profile',         [ProfileController::class, 'update']);
$router->get('/change-password',  [AuthController::class, 'showChangePassword']);
$router->post('/change-password', [AuthController::class, 'changePassword']);

$router->dispatch();
