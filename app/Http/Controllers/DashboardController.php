<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'companies' => Company::count(),
            'channels' => SalesChannel::count(),
            'statuses' => OrderStatus::count(),
            'unmappedStatuses' => OrderStatus::whereNull('baselinker_status_id')->count(),
        ]);
    }
}
