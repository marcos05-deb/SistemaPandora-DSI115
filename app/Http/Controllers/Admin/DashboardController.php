<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardAlertOverviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard (gráficas agregadas sin carnets).
     */
    public function index(Request $request, DashboardAlertOverviewService $overview): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'alertOverview' => $overview->forUser($request->user()),
        ]);
    }
}
