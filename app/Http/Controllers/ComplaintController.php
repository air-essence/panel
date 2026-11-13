<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\ComplaintResolution;
use App\Enums\ProductLocation;
use App\Http\Controllers\Concerns\TracksChanges;
use App\Models\Order;
use App\Models\OrderComplaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    use TracksChanges;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'gdzie' => ['nullable', Rule::enum(ProductLocation::class)],
            'status' => ['nullable', Rule::in(['otwarte', 'wszystkie', 'bez_odpowiedzi', ...array_column(CaseStatus::cases(), 'value')])],
        ]);
        $status = $filters['status'] ?? 'otwarte';

        $complaints = OrderComplaint::with(['order.salesChannel', 'assignee'])
            ->when(in_array($status, ['otwarte', 'bez_odpowiedzi'], true), fn ($q) => $q->open())
            ->when($status === 'bez_odpowiedzi', fn ($q) => $q->whereNull('responded_on'))
            ->when(! in_array($status, ['otwarte', 'wszystkie', 'bez_odpowiedzi'], true), fn ($q) => $q->where('status', $status))
            ->when($filters['gdzie'] ?? null, fn ($q, $where) => $q->where('product_location', $where))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('ean', $term)
                ->orWhere('wz_number', $term)
                ->orWhere('product_name', 'like', "%{$term}%")
                ->orWhereHas('order', fn ($o) => $o
                    ->where('baselinker_order_id', $term)
                    ->orWhere('shop_order_id', $term)
                    ->orWhere('customer_name', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('complaints.index', ['complaints' => $complaints, 'filters' => $filters + ['status' => $status]]);
    }

    public function create(Request $request): View
    {
        $order = $request->filled('zamowienie') ? Order::with('items')->find($request->integer('zamowienie')) : null;

        return view('complaints.form', [
            'complaint' => new OrderComplaint(['reported_on' => now(), 'assigned_to' => $request->user()->id]),
            'order' => $order,
            'users' => $this->activeUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['order_number' => ['required', 'string', 'max:50']]);
        $order = $this->findOrder($request->string('order_number')->trim()->value());
        $data = $this->validated($request, $order);

        $complaint = DB::transaction(function () use ($order, $data, $request) {
            $complaint = OrderComplaint::create($data + [
                'order_id' => $order->id,
                'created_by' => $request->user()->id,
                'closed_at' => $data['status'] === CaseStatus::Zakonczona->value ? now() : null,
            ]);
            $complaint->events()->create(['user_id' => $request->user()->id, 'action' => 'utworzono', 'created_at' => now()]);

            return $complaint;
        });

        return redirect()->route('complaints.show', $complaint)->with('status', 'Zapisano reklamację.');
    }

    public function show(OrderComplaint $complaint): View
    {
        $complaint->load(['order.salesChannel.company', 'order.status', 'author', 'assignee', 'events.user']);

        return view('complaints.show', ['complaint' => $complaint]);
    }

    public function edit(OrderComplaint $complaint): View
    {
        return view('complaints.form', ['complaint' => $complaint, 'order' => $complaint->order->load('items'), 'users' => $this->activeUsers()]);
    }

    public function update(Request $request, OrderComplaint $complaint): RedirectResponse
    {
        $complaint->fill($this->validated($request, $complaint->order, $complaint));

        if ($complaint->isDirty('status')) {
            $complaint->closed_at = $complaint->status === CaseStatus::Zakonczona ? now() : null;
        }

        $changes = $this->changes($complaint, OrderComplaint::TRACKED);

        if ($changes === []) {
            return redirect()->route('complaints.show', $complaint)->with('status', 'Brak zmian do zapisania.');
        }

        DB::transaction(function () use ($complaint, $changes, $request) {
            $complaint->save();
            $complaint->events()->create([
                'user_id' => $request->user()->id,
                'action' => 'zmieniono',
                'changes' => $changes,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('complaints.show', $complaint)->with('status', 'Zapisano zmiany.');
    }

    public function destroy(OrderComplaint $complaint): RedirectResponse
    {
        $complaint->delete();

        return redirect()->route('complaints.index')->with('status', 'Usunięto reklamację.');
    }

    private function findOrder(string $number): Order
    {
        $order = ctype_digit($number) ? Order::where('baselinker_order_id', $number)->first() : null;
        $order ??= Order::where('shop_order_id', $number)->orWhere('external_order_id', $number)->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_number' => 'Nie znaleziono zamówienia o tym numerze. Sprawdź numer albo poczekaj na synchronizację z BaseLinkerem (co 5 minut).',
            ]);
        }

        return $order;
    }

    private function validated(Request $request, Order $order, ?OrderComplaint $complaint = null): array
    {
        $inService = $request->input('product_location') === ProductLocation::WSerwisie->value;

        $data = $request->validate([
            'status' => [$complaint ? 'required' : 'nullable', Rule::enum(CaseStatus::class)],
            'order_item_id' => ['nullable', 'integer', Rule::exists('order_items', 'id')->where('order_id', $order->id)],
            'product_name' => ['nullable', 'required_without:order_item_id', 'string', 'max:255'],
            'ean' => ['nullable', 'string', 'max:20'],
            'reported_on' => ['required', 'date', 'before_or_equal:today'],
            'defect_description' => ['required', 'string', 'min:5', 'max:5000'],
            'product_location' => ['required', Rule::enum(ProductLocation::class)],
            'service_name' => ['nullable', Rule::requiredIf($inService), 'string', 'max:100'],
            'service_sent_on' => ['nullable', Rule::requiredIf($inService), 'date', 'before_or_equal:today'],
            'service_returned_on' => ['nullable', 'date', 'before_or_equal:today'],
            'responded_on' => ['nullable', 'date', 'before_or_equal:today'],
            'resolution' => ['nullable', Rule::enum(ComplaintResolution::class)],
            'resolved_on' => ['nullable', 'required_with:resolution', 'date', 'before_or_equal:today'],
            'wz_number' => ['nullable', 'string', 'max:100'],
            'drive_link' => ['nullable', 'url', 'max:500'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($itemId = $data['order_item_id'] ?? null) {
            $item = $order->items()->findOrFail($itemId);
            $data['product_name'] = $item->name;
            $data['ean'] = ($data['ean'] ?? null) ?: $item->ean;
        }
        unset($data['order_item_id']);

        $data['status'] ??= CaseStatus::Nowa->value;

        if ($data['status'] === CaseStatus::Zakonczona->value) {
            $missing = array_filter([
                empty($data['responded_on']) ? 'datę odpowiedzi dla klienta' : null,
                empty($data['resolution']) ? 'rozstrzygnięcie' : null,
                ($data['resolution'] ?? null) === ComplaintResolution::NowyProdukt->value && empty($data['wz_number']) ? 'numer WZ wysłanego produktu' : null,
                $data['product_location'] === ProductLocation::WSerwisie->value ? 'gdzie jest produkt (wciąż w serwisie)' : null,
            ]);

            if ($missing) {
                throw ValidationException::withMessages([
                    'status' => 'Nie można zakończyć reklamacji. Uzupełnij: '.implode(', ', $missing).'.',
                ]);
            }
        }

        foreach (['ean', 'service_name', 'service_sent_on', 'service_returned_on', 'responded_on', 'resolution', 'resolved_on', 'wz_number', 'drive_link', 'notes', 'assigned_to'] as $field) {
            $data[$field] ??= null;
        }

        return $data;
    }
}
