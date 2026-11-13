<?php

namespace App\Http\Controllers;

use App\Enums\Severity;
use App\Models\DiscrepancyDismissal;
use App\Models\Order;
use App\Services\Discrepancies\DiscrepancyFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiscrepancyController extends Controller
{
    public function index(Request $request, DiscrepancyFinder $finder): View
    {
        $filters = $request->validate([
            'waga' => ['nullable', Rule::enum(Severity::class)],
            'regula' => ['nullable', Rule::in(array_keys(DiscrepancyFinder::RULES))],
        ]);

        $all = $finder->find();

        return view('discrepancies.index', [
            'discrepancies' => $all
                ->when($filters['waga'] ?? null, fn ($c, $s) => $c->filter(fn ($d) => $d->severity->value === $s))
                ->when($filters['regula'] ?? null, fn ($c, $r) => $c->filter(fn ($d) => $d->rule === $r))
                ->values(),
            'counts' => $all->countBy(fn ($d) => $d->rule),
            'total' => $all->count(),
            'filters' => $filters,
            'dismissals' => DiscrepancyDismissal::with(['order', 'user'])->latest()->limit(50)->get(),
        ]);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
            'rule' => ['required', Rule::in(array_keys(DiscrepancyFinder::RULES))],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DiscrepancyDismissal::updateOrCreate(
            ['order_id' => $data['order_id'], 'rule' => $data['rule']],
            ['note' => $data['note'], 'user_id' => $request->user()->id],
        );

        $order = Order::find($data['order_id']);

        return back()->with('status', "Oznaczono jako wyjaśnione: zamówienie {$order->baselinker_order_id}.");
    }

    public function restore(DiscrepancyDismissal $dismissal): RedirectResponse
    {
        $dismissal->delete();

        return back()->with('status', 'Przywrócono rozbieżność na listę.');
    }
}
