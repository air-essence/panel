@extends('layouts.app')
@section('title', 'Zamówienie '.$order->baselinker_order_id)
@section('content')
<p><a href="{{ route('orders.index') }}">← Zamówienia</a></p>
<h1>Zamówienie {{ $order->baselinker_order_id }}</h1>
@foreach ($discrepancies as $d)
    @include('discrepancies.item', ['d' => $d])
@endforeach
<div class="card">
    <dl class="details">
        <dt>Kanał</dt><dd>{!! $order->salesChannel ? e($order->salesChannel->name) : '<span class="badge badge-warn">bez kanału</span> <span class="muted">'.e($order->baselinker_source).'</span>' !!}</dd>
        <dt>Spółka</dt><dd>{{ $order->salesChannel?->company?->name ?? '—' }}</dd>
        <dt>Nr w sklepie</dt><dd>{{ $order->shop_order_id ?? '—' }}</dd>
        <dt>Nr zewnętrzny</dt><dd>{{ $order->external_order_id ?? '—' }}</dd>
        <dt>Status</dt><dd>@include('orders.status', ['order' => $order])</dd>
        <dt>Data zamówienia</dt><dd>{{ $order->ordered_at?->format('d.m.Y H:i') ?? '—' }}</dd>
        <dt>Status od</dt><dd>{{ $order->status_changed_at?->format('d.m.Y H:i') ?? '—' }}</dd>
        <dt>Klient</dt><dd>{{ $order->customer_name ?? '—' }} · {{ $order->email ?? '—' }} · {{ $order->phone ?? '—' }}</dd>
        <dt>Dostawa</dt><dd>{{ $order->delivery_method ?? '—' }}</dd>
        <dt>Przesyłka</dt><dd>{{ $order->tracking_number ?? '—' }} <span class="muted">{{ $order->carrier }}</span></dd>
        <dt>Kwota</dt><dd>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }} (wpłacono {{ number_format((float) $order->paid, 2, ',', ' ') }})</dd>
        <dt>Płatność</dt><dd>@include('orders.payment', ['order' => $order])</dd>
        <dt>Pobrano z BaseLinkera</dt><dd>{{ $order->synced_at?->format('d.m.Y H:i') ?? '—' }}</dd>
    </dl>
</div>
<h2>Sprawy</h2>
@php($openCancellation = $order->cases->first(fn ($c) => $c->type === \App\Models\OrderCase::TYPE_CANCELLATION && $c->status->isOpen()))
@if (! $openCancellation)<p><a class="btn btn-primary" href="{{ route('cancellations.create', ['zamowienie' => $order->id]) }}">Zgłoś anulację</a></p>@endif
<table>
    <thead><tr><th>Nr</th><th>Typ</th><th>Status</th><th>Data</th></tr></thead>
    <tbody>
    @forelse ($order->cases as $case)
        <tr><td><a href="{{ route('cancellations.show', $case) }}">{{ $case->id }}</a></td><td>Anulacja</td><td>{{ $case->status->label() }}</td><td>{{ $case->requested_on->format('d.m.Y') }}</td></tr>
    @empty
        <tr><td colspan="4" class="muted">Brak spraw do tego zamówienia.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Produkty</h2>
<table>
    <thead><tr><th>Nazwa</th><th>SKU</th><th>EAN</th><th>Ilość</th><th>Cena brutto</th></tr></thead>
    <tbody>
    @forelse ($order->items as $item)
        <tr><td>{{ $item->name }}</td><td>{{ $item->sku ?? '—' }}</td><td>{{ $item->ean ?? '—' }}</td><td>{{ $item->quantity }}</td><td>{{ number_format((float) $item->price, 2, ',', ' ') }}</td></tr>
    @empty
        <tr><td colspan="5" class="muted">Brak produktów.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
