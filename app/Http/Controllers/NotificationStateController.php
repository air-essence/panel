<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\SyncRun;
use App\Services\BaseLinker\OrderSync;
use App\Services\Discrepancies\Discrepancy;
use App\Services\Discrepancies\DiscrepancyFinder;
use Illuminate\Http\JsonResponse;

/**
 * Stan do odświeżania w przeglądarce co minutę: licznik w menu i powiadomienia systemowe.
 */
class NotificationStateController extends Controller
{
    public function __invoke(DiscrepancyFinder $finder): JsonResponse
    {
        $all = $finder->find();
        $lastSync = SyncRun::latestFor(OrderSync::SOURCE);

        return response()->json([
            'total' => $all->count(),
            'critical' => $all
                ->filter(fn (Discrepancy $d) => $d->severity === Severity::Krytyczna)
                ->map(fn (Discrepancy $d) => [
                    'key' => $d->order->id.'|'.$d->rule,
                    'title' => DiscrepancyFinder::RULES[$d->rule].' · '.$d->order->baselinker_order_id,
                    'message' => $d->message,
                    'url' => route('orders.show', $d->order),
                ])
                ->values(),
            'sync_error' => $lastSync && $lastSync->finished_at && $lastSync->error ? [
                'key' => 'sync|'.$lastSync->id,
                'title' => 'Błąd synchronizacji z BaseLinkerem',
                'message' => $lastSync->error,
                'url' => route('dashboard'),
            ] : null,
        ]);
    }
}
