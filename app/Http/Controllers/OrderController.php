<?php

namespace App\Http\Controllers;

use App\Enums\StatusGroup;
use App\Models\Order;
use App\Models\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kanal' => ['nullable', 'integer'],
            'grupa' => ['nullable', Rule::enum(StatusGroup::class)],
        ]);

        $orders = Order::with(['salesChannel', 'status'])
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $query->where(fn ($w) => $w
                    ->where('baselinker_order_id', $q)
                    ->orWhere('shop_order_id', $q)
                    ->orWhere('external_order_id', $q)
                    ->orWhere('tracking_number', $q)
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%"));
            })
            ->when($filters['kanal'] ?? null, fn ($query, $id) => $query->where('sales_channel_id', $id))
            ->when($filters['grupa'] ?? null, fn ($query, $group) => $query->whereHas('status', fn ($s) => $s->where('group', $group)))
            ->latest('ordered_at')
            ->paginate(50)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'channels' => SalesChannel::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Order $order): View
    {
        return view('orders.show', ['order' => $order->load(['salesChannel.company', 'status', 'items'])]);
    }
}
