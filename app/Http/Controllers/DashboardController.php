<?php

namespace App\Http\Controllers;

use App\Models\BaselinkerSource;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\SyncRun;
use App\Services\BaseLinker\OrderSync;
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
            'orders' => Order::count(),
            'ordersWithoutChannel' => Order::whereNull('sales_channel_id')->count(),
            'unassignedSources' => BaselinkerSource::doesntHave('salesChannel')->orderBy('type')->orderBy('name')->get(),
            'lastSync' => SyncRun::latestFor(OrderSync::SOURCE),
            'tokenConfigured' => filled(config('services.baselinker.token')),
        ]);
    }
}
