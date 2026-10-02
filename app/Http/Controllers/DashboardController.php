<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Services\OeeDashboard;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, OeeDashboard $dashboard): View
    {
        return view('dashboard', [
            'dashboard' => $dashboard->build($request->validated('date'), $request->shift()),
            'activeTab' => $request->validated('tab') ?? 'overview',
        ]);
    }
}
