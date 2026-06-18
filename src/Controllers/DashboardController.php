<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\View;
use App\Models\Dashboard;

final class DashboardController
{
    /** GET /dashboard */
    public function index(): void
    {
        Auth::requireLogin();

        View::render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'active'    => 'dashboard',
            'stats'     => $this->collectStats(),
            'docTypes'  => Dashboard::docTypeBreakdown(),
            'activity'  => Dashboard::recentActivity(10),
        ]);
    }

    /**
     * GET /dashboard/stats — AJAX refresh of Zone A (stat cards only).
     * Returns an HTML fragment for in-place replacement.
     */
    public function stats(): void
    {
        Auth::requireLogin();

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo View::renderPartial('dashboard/_stats', [
            'stats'    => $this->collectStats(),
            'docTypes' => Dashboard::docTypeBreakdown(),
        ]);
    }

    /** @return array<string, array<string,mixed>> */
    private function collectStats(): array
    {
        $out = [];
        foreach (Dashboard::APPS as $app) {
            $out[$app] = Dashboard::appStats($app);
        }
        return $out;
    }
}
