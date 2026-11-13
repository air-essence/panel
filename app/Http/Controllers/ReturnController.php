<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Enums\GoodsStatus;
use App\Enums\RefundMethod;
use App\Enums\ReturnKind;
use App\Enums\SaleDocument;
use App\Http\Controllers\Concerns\TracksChanges;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReturnController extends Controller
{
    use TracksChanges;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'spolka' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['otwarte', 'wszystkie', 'po_terminie', ...array_column(CaseStatus::cases(), 'value')])],
        ]);
        $status = $filters['status'] ?? 'otwarte';

        $returns = OrderReturn::with(['order.salesChannel.company', 'assignee'])
            ->when(in_array($status, ['otwarte', 'po_terminie'], true), fn ($q) => $q->open())
            ->when($status === 'po_terminie', fn ($q) => $q->whereNull('refunded_on')
                ->where('kind', ReturnKind::Odstapienie->value)
                ->where('requested_on', '<', today()->subDays(OrderReturn::REFUND_DEADLINE_DAYS)))
            ->when(! in_array($status, ['otwarte', 'wszystkie', 'po_terminie'], true), fn ($q) => $q->where('status', $status))
            ->when($filters['spolka'] ?? null, fn ($q, $id) => $q->whereHas('order.salesChannel', fn ($c) => $c->where('company_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('sale_document_number', $term)
                ->orWhere('correction_document_number', $term)
                ->orWhere('return_tracking_number', $term)
                ->orWhereHas('order', fn ($o) => $o
                    ->where('baselinker_order_id', $term)
                    ->orWhere('shop_order_id', $term)
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('returns.index', [
            'returns' => $returns,
            'companies' => Company::orderBy('name')->get(),
            'filters' => $filters + ['status' => $status],
        ]);
    }

    public function create(Request $request): View
    {
        $order = $request->filled('zamowienie') ? Order::find($request->integer('zamowienie')) : null;

        return view('returns.form', [
            'return' => new OrderReturn([
                'requested_on' => now(),
                'assigned_to' => $request->user()->id,
                'amount' => $order && (float) $order->paid > 0 ? $order->paid : null,
            ]),
            'order' => $order,
            'users' => $this->activeUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['order_number' => ['required', 'string', 'max:50']]);
        $data = $this->validated($request);
        $order = $this->findOrder($request->string('order_number')->trim()->value());

        $return = DB::transaction(function () use ($order, $data, $request) {
            $return = OrderReturn::create($data + [
                'order_id' => $order->id,
                'created_by' => $request->user()->id,
                'closed_at' => $data['status'] === CaseStatus::Zakonczona->value ? now() : null,
            ]);
            $return->events()->create(['user_id' => $request->user()->id, 'action' => 'utworzono', 'created_at' => now()]);

            return $return;
        });

        return redirect()->route('returns.show', $return)->with('status', 'Zapisano zwrot.');
    }

    public function show(OrderReturn $return): View
    {
        $return->load(['order.salesChannel.company', 'order.status', 'order.returns', 'author', 'assignee', 'events.user']);

        return view('returns.show', ['return' => $return]);
    }

    public function edit(OrderReturn $return): View
    {
        return view('returns.form', ['return' => $return, 'order' => $return->order, 'users' => $this->activeUsers()]);
    }

    public function update(Request $request, OrderReturn $return): RedirectResponse
    {
        $return->fill($this->validated($request, $return));

        if ($return->isDirty('status')) {
            $return->closed_at = $return->status === CaseStatus::Zakonczona ? now() : null;
        }

        $changes = $this->changes($return, OrderReturn::TRACKED);

        if ($changes === []) {
            return redirect()->route('returns.show', $return)->with('status', 'Brak zmian do zapisania.');
        }

        DB::transaction(function () use ($return, $changes, $request) {
            $return->save();
            $return->events()->create([
                'user_id' => $request->user()->id,
                'action' => 'zmieniono',
                'changes' => $changes,
                'created_at' => now(),
            ]);
        });

        return redirect()->route('returns.show', $return)->with('status', 'Zapisano zmiany.');
    }

    public function destroy(OrderReturn $return): RedirectResponse
    {
        $return->delete();

        return redirect()->route('returns.index')->with('status', 'Usunięto zwrot.');
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

    private function validated(Request $request, ?OrderReturn $return = null): array
    {
        $request->merge(['refund_account' => preg_replace('/\s+/', '', (string) $request->input('refund_account')) ?: null]);

        $data = $request->validate([
            'status' => [$return ? 'required' : 'nullable', Rule::enum(CaseStatus::class)],
            'kind' => ['required', Rule::enum(ReturnKind::class)],
            'requested_on' => ['required', 'date', 'before_or_equal:today'],
            'goods_status' => ['required', Rule::enum(GoodsStatus::class)],
            'received_on' => ['nullable', Rule::requiredIf(fn () => GoodsStatus::tryFrom((string) $request->input('goods_status'))?->arrived()), 'date', 'before_or_equal:today'],
            'goods_location' => ['nullable', Rule::requiredIf(fn () => GoodsStatus::tryFrom((string) $request->input('goods_status'))?->arrived()), 'string', 'max:100'],
            'return_tracking_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'refund_method' => ['required', Rule::enum(RefundMethod::class)],
            'refund_account' => ['nullable', 'required_if:refund_method,'.RefundMethod::Przelew->value, 'regex:/^(PL)?\d{26}$/i'],
            'refunded_on' => ['nullable', 'date', 'before_or_equal:today'],
            'sale_document' => ['required', Rule::enum(SaleDocument::class)],
            'sale_document_number' => ['required', 'string', 'max:100'],
            'correction_document_number' => ['nullable', 'string', 'max:100'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'refund_account.regex' => 'Numer konta musi mieć 26 cyfr (można poprzedzić go „PL”).',
        ]);

        $data['status'] ??= CaseStatus::Nowa->value;
        $kind = ReturnKind::from($data['kind']);
        $goods = GoodsStatus::from($data['goods_status']);

        if ($kind->needsGoods() === ($goods === GoodsStatus::NieDotyczy)) {
            throw ValidationException::withMessages([
                'goods_status' => $kind->needsGoods()
                    ? 'Przy zwrocie towaru wybierz, czy paczka dotarła.'
                    : 'Przy rabacie towar nie wraca. Wybierz „Nie dotyczy (rabat)”.',
            ]);
        }

        if (! $goods->arrived()) {
            $data['received_on'] = null;
        }

        if (($data['refund_method'] ?? null) !== RefundMethod::Przelew->value) {
            $data['refund_account'] = null;
        }

        if ($data['status'] === CaseStatus::Zakonczona->value) {
            $missing = array_filter([
                empty($data['refunded_on']) ? 'datę zwrotu pieniędzy' : null,
                empty($data['correction_document_number']) ? 'numer korekty albo PZ' : null,
                $kind->needsGoods() && ! $goods->arrived() ? 'informację, że towar dotarł' : null,
            ]);

            if ($missing) {
                throw ValidationException::withMessages([
                    'status' => 'Nie można zakończyć zwrotu. Uzupełnij: '.implode(', ', $missing).'.',
                ]);
            }
        }

        foreach (['received_on', 'goods_location', 'return_tracking_number', 'refunded_on', 'correction_document_number', 'notes', 'assigned_to'] as $field) {
            $data[$field] ??= null;
        }

        return $data;
    }
}
