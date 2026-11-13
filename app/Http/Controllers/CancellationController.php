<?php

namespace App\Http\Controllers;

use App\Enums\CancellationReason;
use App\Enums\CaseStatus;
use App\Enums\RefundStatus;
use App\Enums\ShipmentHold;
use App\Http\Controllers\Concerns\TracksChanges;
use App\Models\Order;
use App\Models\OrderCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CancellationController extends Controller
{
    use TracksChanges;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['otwarte', 'wszystkie', ...array_column(CaseStatus::cases(), 'value')])],
        ]);
        $status = $filters['status'] ?? 'otwarte';

        $cases = OrderCase::cancellations()
            ->with(['order.salesChannel', 'order.status', 'assignee'])
            ->when($status === 'otwarte', fn ($q) => $q->open())
            ->when(! in_array($status, ['otwarte', 'wszystkie'], true), fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('order', fn ($o) => $o
                ->where('baselinker_order_id', $term)
                ->orWhere('shop_order_id', $term)
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('cancellations.index', ['cases' => $cases, 'filters' => $filters + ['status' => $status]]);
    }

    public function create(Request $request): View
    {
        $order = $request->filled('zamowienie') ? Order::find($request->integer('zamowienie')) : null;

        return view('cancellations.form', [
            'case' => new OrderCase(['requested_on' => now(), 'assigned_to' => $request->user()->id]),
            'order' => $order,
            'users' => $this->activeUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['order_number' => ['required', 'string', 'max:50']]);
        $data = $this->validated($request);
        $order = $this->findOrder($request->string('order_number')->trim()->value());

        $case = DB::transaction(function () use ($order, $data, $request) {
            $case = OrderCase::create($data + [
                'order_id' => $order->id,
                'created_by' => $request->user()->id,
                'closed_at' => $data['status'] === CaseStatus::Zakonczona->value ? now() : null,
            ]);
            $case->events()->create(['user_id' => $request->user()->id, 'action' => 'utworzono', 'created_at' => now()]);

            return $case;
        });

        return redirect()->route('cancellations.show', $case)->with('status', 'Zapisano anulację.');
    }

    public function show(OrderCase $cancellation): View
    {
        $cancellation->load(['order.salesChannel.company', 'order.status', 'author', 'assignee', 'events.user']);

        return view('cancellations.show', ['case' => $cancellation, 'warnings' => $cancellation->warnings()]);
    }

    public function edit(OrderCase $cancellation): View
    {
        return view('cancellations.form', [
            'case' => $cancellation,
            'order' => $cancellation->order,
            'users' => $this->activeUsers(),
        ]);
    }

    public function update(Request $request, OrderCase $cancellation): RedirectResponse
    {
        $data = $this->validated($request, $cancellation);
        $cancellation->fill($data);

        if ($cancellation->isDirty('status')) {
            $cancellation->closed_at = $cancellation->status === CaseStatus::Zakonczona ? now() : null;
        }

        $changes = $this->changes($cancellation, OrderCase::TRACKED);

        if ($changes === []) {
            return redirect()->route('cancellations.show', $cancellation)->with('status', 'Brak zmian do zapisania.');
        }

        DB::transaction(function () use ($cancellation, $changes, $request) {
            $cancellation->save();
            $cancellation->events()->create([
                'user_id' => $request->user()->id,
                'action' => 'zmieniono',
                'changes' => $changes,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('cancellations.show', $cancellation)->with('status', 'Zapisano zmiany.');
    }

    public function destroy(OrderCase $cancellation): RedirectResponse
    {
        $cancellation->delete();

        return redirect()->route('cancellations.index')->with('status', 'Usunięto sprawę.');
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

        $open = $order->cases()->cancellations()->open()->first();

        if ($open) {
            throw ValidationException::withMessages([
                'order_number' => "To zamówienie ma już otwartą anulację (sprawa nr {$open->id}).",
            ]);
        }

        return $order;
    }

    private function validated(Request $request, ?OrderCase $case = null): array
    {
        $data = $request->validate([
            'status' => [$case ? 'required' : 'nullable', Rule::enum(CaseStatus::class)],
            'reason' => ['required', Rule::enum(CancellationReason::class)],
            'reason_details' => ['nullable', 'required_if:reason,'.CancellationReason::Inny->value, 'string', 'max:255'],
            'requested_on' => ['required', 'date', 'before_or_equal:today'],
            'refund_status' => ['required', Rule::enum(RefundStatus::class)],
            'refund_amount' => ['nullable', 'required_unless:refund_status,'.RefundStatus::NieDotyczy->value, 'numeric', 'min:0.01', 'max:1000000'],
            'refunded_on' => ['nullable', 'required_if:refund_status,'.RefundStatus::Zwrocono->value, 'date', 'before_or_equal:today'],
            'shipment_hold' => ['required', Rule::enum(ShipmentHold::class)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['status'] ??= CaseStatus::Nowa->value;

        if ($data['refund_status'] === RefundStatus::NieDotyczy->value) {
            $data['refund_amount'] = null;
        }

        if ($data['refund_status'] !== RefundStatus::Zwrocono->value) {
            $data['refunded_on'] = null;
        }

        if ($data['status'] === CaseStatus::Zakonczona->value && $data['refund_status'] === RefundStatus::DoZwrotu->value) {
            throw ValidationException::withMessages([
                'status' => 'Nie można zakończyć anulacji, dopóki pieniądze nie zostały zwrócone. Ustaw „Zwrot pieniędzy” na „Zwrócono” i podaj datę.',
            ]);
        }

        foreach (['reason_details', 'notes', 'assigned_to'] as $field) {
            $data[$field] ??= null;
        }

        return $data;
    }
}
