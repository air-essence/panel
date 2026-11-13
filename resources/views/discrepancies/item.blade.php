@php($case = $d->order->cases->first())
@php($complaint = str_starts_with($d->rule, 'reklamacja_') ? $d->order->complaints->first() : null)
@php($return = in_array($d->rule, ['zwrot_po_terminie', 'zwrot_wiekszy_niz_wplata', 'pieniadze_bez_towaru'], true) ? $d->order->returns->first() : null)
<div class="issue issue-{{ $d->severity->value }}">
    <div>
        <span class="badge {{ $d->severity === \App\Enums\Severity::Krytyczna ? 'badge-danger' : 'badge-warn' }}">{{ $d->severity->label() }}</span>
        <strong>{{ \App\Services\Discrepancies\DiscrepancyFinder::RULES[$d->rule] }}</strong>
        · zamówienie <a href="{{ route('orders.show', $d->order) }}">{{ $d->order->baselinker_order_id }}</a>
        <span class="muted">{{ $d->order->customer_name }}@if ($d->order->salesChannel) · {{ $d->order->salesChannel->name }}@endif</span>
        @if ($complaint) · <a href="{{ route('complaints.show', $complaint) }}">reklamacja nr {{ $complaint->id }}</a>@elseif ($return) · <a href="{{ route('returns.show', $return) }}">zwrot nr {{ $return->id }}</a>@elseif ($case) · <a href="{{ route('cancellations.show', $case) }}">anulacja nr {{ $case->id }}</a>@endif
    </div>
    <div>{{ $d->message }}</div>
    @if (empty($compact))
        <form method="post" action="{{ route('discrepancies.dismiss') }}">
            @csrf
            <input type="hidden" name="order_id" value="{{ $d->order->id }}">
            <input type="hidden" name="rule" value="{{ $d->rule }}">
            <input type="text" name="note" required minlength="5" maxlength="500" placeholder="Wyjaśnienie (np. paczka zatrzymana u kuriera)">
            <button class="btn">Oznacz jako wyjaśnione</button>
            @if ($d->rule === 'reklamacja_tylko_w_baselinkerze')<a class="btn" href="{{ route('complaints.create', ['zamowienie' => $d->order->id]) }}">Dodaj reklamację</a>@endif
            @if ($d->rule === 'reklamacja_zwrot_bez_zwrotu')<a class="btn" href="{{ route('returns.create', ['zamowienie' => $d->order->id]) }}">Dodaj zwrot</a>@endif
            @if ($d->rule === 'anulacja_tylko_w_baselinkerze')<a class="btn" href="{{ route('cancellations.create', ['zamowienie' => $d->order->id]) }}">Dodaj anulację</a>@endif
        </form>
    @endif
</div>
