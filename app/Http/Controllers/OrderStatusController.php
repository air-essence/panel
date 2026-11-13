<?php

namespace App\Http\Controllers;

use App\Enums\StatusGroup;
use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function index(): View
    {
        return view('statuses.index', ['statuses' => OrderStatus::orderBy('group')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('statuses.form', ['status' => new OrderStatus]);
    }

    public function store(Request $request): RedirectResponse
    {
        OrderStatus::create($this->validated($request));

        return redirect()->route('statuses.index')->with('status', 'Dodano status.');
    }

    public function edit(OrderStatus $status): View
    {
        return view('statuses.form', ['status' => $status]);
    }

    public function update(Request $request, OrderStatus $status): RedirectResponse
    {
        $status->update($this->validated($request, $status));

        return redirect()->route('statuses.index')->with('status', 'Zapisano zmiany.');
    }

    public function destroy(OrderStatus $status): RedirectResponse
    {
        $status->delete();

        return redirect()->route('statuses.index')->with('status', 'Usunięto status.');
    }

    private function validated(Request $request, ?OrderStatus $status = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('order_statuses')->ignore($status)],
            'group' => ['required', Rule::enum(StatusGroup::class)],
            'baselinker_status_id' => ['nullable', 'integer', 'min:1', Rule::unique('order_statuses')->ignore($status)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
