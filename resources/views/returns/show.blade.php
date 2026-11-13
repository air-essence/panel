@extends('layouts.app')
@section('title', 'Zwrot nr '.$return->id)
@section('content')
<p><a href="{{ route('returns.index') }}">← Zwroty</a></p>
<h1>Zwrot nr {{ $return->id }} <span class="badge">{{ $return->status->label() }}</span></h1>
@php($order = $return->order)
<div class="card">
    <dl class="details">
        <dt>Zamówienie</dt><dd><a href="{{ route('orders.show', $order) }}">{{ $order->baselinker_order_id }}</a> · {{ $order->salesChannel?->name ?? 'bez kanału' }}</dd>
        <dt>Spółka</dt><dd>{!! $order->salesChannel?->company ? e($order->salesChannel->company->name) : '<span class="badge badge-warn">kanał bez spółki</span>' !!}</dd>
        <dt>Klient</dt><dd>{{ $order->customer_name }} · {{ $order->email }}</dd>
        <dt>Wpłacono</dt><dd>{{ number_format((float) $order->paid, 2, ',', ' ') }} {{ $order->currency }} · {{ $order->payment_method }}</dd>
        <dt>Rodzaj</dt><dd>{{ $return->kind->label() }}</dd>
        <dt>Zgłoszono</dt><dd>{{ $return->requested_on->format('d.m.Y') }}</dd>
        <dt>Towar</dt><dd>{{ $return->goods_status->label() }}@if ($return->received_on) · dotarł {{ $return->received_on->format('d.m.Y') }}@endif @if ($return->goods_location) · {{ $return->goods_location }}@endif</dd>
        <dt>Przesyłka zwrotna</dt><dd>{{ $return->return_tracking_number ?? '—' }}</dd>
        <dt>Kwota zwrotu</dt><dd>{{ number_format((float) $return->amount, 2, ',', ' ') }} {{ $order->currency }}</dd>
        <dt>Metoda</dt><dd>{{ $return->refund_method->label() }}@if ($return->refund_account) · konto {{ $return->refund_account }}@endif</dd>
        <dt>Pieniądze</dt><dd>@include('returns.refund', ['return' => $return])</dd>
        <dt>Dokument sprzedaży</dt><dd>{{ $return->sale_document->label() }} {{ $return->sale_document_number }}</dd>
        <dt>Korekta / PZ</dt><dd>{{ $return->correction_document_number ?? '—' }}</dd>
        <dt>Prowadzi</dt><dd>{{ $return->assignee?->name ?? '—' }}</dd>
        <dt>Dodał(a)</dt><dd>{{ $return->author->name }} · {{ $return->created_at->format('d.m.Y H:i') }}</dd>
        @if ($return->closed_at)<dt>Zakończono</dt><dd>{{ $return->closed_at->format('d.m.Y H:i') }}</dd>@endif
        <dt>Uwagi</dt><dd style="white-space:pre-line">{{ $return->notes ?? '—' }}</dd>
    </dl>
</div>
<div class="row-actions" style="margin-bottom:16px">
    <a class="btn btn-primary" href="{{ route('returns.edit', $return) }}">Edytuj</a>
    @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('returns.destroy', $return) }}" onsubmit="return confirm('Usunąć zwrot razem z historią?')">@csrf @method('delete')<button class="btn btn-danger">Usuń</button></form>
    @endif
</div>

<h2>Historia zmian</h2>
<table>
    <thead><tr><th>Kiedy</th><th>Kto</th><th>Co</th></tr></thead>
    <tbody>
    @foreach ($return->events as $event)
        <tr>
            <td>{{ $event->created_at->format('d.m.Y H:i') }}</td>
            <td>{{ $event->user?->name ?? '—' }}</td>
            <td>
                @if ($event->action === 'utworzono')
                    Dodano zwrot
                @else
                    @foreach ($event->changes ?? [] as $field => [$old, $new])
                        <div>{{ \App\Models\OrderReturn::TRACKED[$field] ?? $field }}: <span class="muted">{{ $old ?? '—' }}</span> → {{ $new ?? '—' }}</div>
                    @endforeach
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
