<?php

namespace App\Http\Controllers;

use App\Models\BaselinkerSource;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderCase;
use App\Models\OrderComplaint;
use App\Models\OrderReturn;
use App\Models\OrderStatus;
use App\Models\SalesChannel;
use App\Models\SyncRun;
use App\Services\BaseLinker\OrderSync;
use App\Services\Discrepancies\DiscrepancyFinder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DiscrepancyFinder $finder): View
    {
        $discrepancies = $finder->find();

        return view('dashboard', [
            'discrepancies' => $discrepancies->take(10),
            'discrepancyCount' => $discrepancies->count(),
            'companies' => Company::count(),
            'channels' => SalesChannel::count(),
            'statuses' => OrderStatus::count(),
            'unmappedStatuses' => OrderStatus::whereNull('baselinker_status_id')->count(),
            'orders' => Order::count(),
            'openCancellations' => OrderCase::cancellations()->open()->count(),
            'openReturns' => OrderReturn::open()->count(),
            'openComplaints' => OrderComplaint::open()->count(),
            'ordersWithoutChannel' => Order::whereNull('sales_channel_id')->count(),
            'unassignedSources' => BaselinkerSource::doesntHave('salesChannel')->orderBy('type')->orderBy('name')->get(),
            'lastSync' => SyncRun::latestFor(OrderSync::SOURCE),
            'tokenConfigured' => filled(config('services.baselinker.token')),
        ]);
    }
}
