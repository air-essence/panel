@php($case = $d->order->cases->first())
<div class="issue issue-{{ $d->severity->value }}">
    <div>
        <span class="badge {{ $d->severity === \App\Enums\Severity::Krytyczna ? 'badge-danger' : 'badge-warn' }}">{{ $d->severity->label() }}</span>
        <strong>{{ \App\Services\Discrepancies\DiscrepancyFinder::RULES[$d->rule] }}</strong>
        · zamówienie <a href="{{ route('orders.show', $d->order) }}">{{ $d->order->baselinker_order_id }}</a>
        <span class="muted">{{ $d->order->customer_name }}@if ($d->order->salesChannel) · {{ $d->order->salesChannel->name }}@endif</span>
        @if ($case) · <a href="{{ route('cancellations.show', $case) }}">anulacja nr {{ $case->id }}</a>@endif
    </div>
    <div>{{ $d->message }}</div>
    @if (empty($compact))
        <form method="post" action="{{ route('discrepancies.dismiss') }}">
            @csrf
            <input type="hidden" name="order_id" value="{{ $d->order->id }}">
            <input type="hidden" name="rule" value="{{ $d->rule }}">
            <input type="text" name="note" required minlength="5" maxlength="500" placeholder="Wyjaśnienie (np. paczka zatrzymana u kuriera)">
            <button class="btn">Oznacz jako wyjaśnione</button>
            @if ($d->rule === 'anulacja_tylko_w_baselinkerze')<a class="btn" href="{{ route('cancellations.create', ['zamowienie' => $d->order->id]) }}">Dodaj anulację</a>@endif
        </form>
    @endif
</div>
