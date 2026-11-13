@extends('layouts.app')
@section('title', 'Anulacja nr '.$case->id)
@section('content')
<p><a href="{{ route('cancellations.index') }}">← Anulacje</a></p>
<h1>Anulacja nr {{ $case->id }} <span class="badge">{{ $case->status->label() }}</span></h1>

@foreach ($warnings as $warning)
    <div class="card" style="border-color:var(--danger);background:#fff5f5"><strong style="color:var(--danger)">Uwaga:</strong> {{ $warning }}</div>
@endforeach

<div class="card">
    <dl class="details">
        <dt>Zamówienie</dt><dd><a href="{{ route('orders.show', $case->order) }}">{{ $case->order->baselinker_order_id }}</a> · {{ $case->order->salesChannel?->name ?? 'bez kanału' }}@if ($case->order->salesChannel?->company) · {{ $case->order->salesChannel->company->name }}@endif</dd>
        <dt>Klient</dt><dd>{{ $case->order->customer_name }} · {{ $case->order->email }}</dd>
        <dt>Status w BaseLinkerze</dt><dd>@include('orders.status', ['order' => $case->order])</dd>
        <dt>Wpłacono</dt><dd>{{ number_format((float) $case->order->paid, 2, ',', ' ') }} {{ $case->order->currency }} · {{ $case->order->payment_method }}</dd>
        <dt>Powód</dt><dd>{{ $case->reason->label() }}@if ($case->reason_details) — {{ $case->reason_details }}@endif</dd>
        <dt>Data zgłoszenia</dt><dd>{{ $case->requested_on->format('d.m.Y') }}</dd>
        <dt>Przesyłka</dt><dd>{{ $case->shipment_hold->label() }}@if ($case->order->tracking_number) <span class="muted">({{ $case->order->tracking_number }})</span>@endif</dd>
        <dt>Zwrot pieniędzy</dt><dd>@include('cancellations.refund', ['case' => $case])</dd>
        <dt>Prowadzi</dt><dd>{{ $case->assignee?->name ?? '—' }}</dd>
        <dt>Zgłosił(a)</dt><dd>{{ $case->author->name }} · {{ $case->created_at->format('d.m.Y H:i') }}</dd>
        @if ($case->closed_at)<dt>Zakończono</dt><dd>{{ $case->closed_at->format('d.m.Y H:i') }}</dd>@endif
        <dt>Uwagi</dt><dd style="white-space:pre-line">{{ $case->notes ?? '—' }}</dd>
    </dl>
</div>
<div class="row-actions" style="margin-bottom:16px">
    <a class="btn btn-primary" href="{{ route('cancellations.edit', $case) }}">Edytuj</a>
    @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('cancellations.destroy', $case) }}" onsubmit="return confirm('Usunąć sprawę razem z historią?')">@csrf @method('delete')<button class="btn btn-danger">Usuń</button></form>
    @endif
</div>

<h2>Historia zmian</h2>
<table>
    <thead><tr><th>Kiedy</th><th>Kto</th><th>Co</th></tr></thead>
    <tbody>
    @foreach ($case->events as $event)
        <tr>
            <td>{{ $event->created_at->format('d.m.Y H:i') }}</td>
            <td>{{ $event->user?->name ?? '—' }}</td>
            <td>
                @if ($event->action === 'utworzono')
                    Założono sprawę
                @else
                    @foreach ($event->changes ?? [] as $field => [$old, $new])
                        <div>{{ \App\Models\OrderCase::TRACKED[$field] ?? $field }}: <span class="muted">{{ $old ?? '—' }}</span> → {{ $new ?? '—' }}</div>
                    @endforeach
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
