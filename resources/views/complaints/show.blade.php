@extends('layouts.app')
@section('title', 'Reklamacja nr '.$complaint->id)
@section('content')
<p><a href="{{ route('complaints.index') }}">← Reklamacje</a></p>
<h1>Reklamacja nr {{ $complaint->id }} <span class="badge">{{ $complaint->status->label() }}</span></h1>
@php($order = $complaint->order)
<div class="card">
    <dl class="details">
        <dt>Zamówienie</dt><dd><a href="{{ route('orders.show', $order) }}">{{ $order->baselinker_order_id }}</a> · {{ $order->salesChannel?->name ?? 'bez kanału' }}@if ($order->salesChannel?->company) · {{ $order->salesChannel->company->name }}@endif</dd>
        <dt>Data zamówienia</dt><dd>{{ $order->ordered_at?->format('d.m.Y') ?? '—' }}</dd>
        <dt>Klient</dt><dd>{{ collect([$order->customer_name, $order->email, $order->phone])->filter()->implode(' · ') }}</dd>
        <dt>Produkt</dt><dd>{{ $complaint->product_name }}@if ($complaint->ean) · EAN {{ $complaint->ean }}@endif</dd>
        <dt>Zgłoszono</dt><dd>{{ $complaint->reported_on->format('d.m.Y') }}</dd>
        <dt>Wada</dt><dd style="white-space:pre-line">{{ $complaint->defect_description }}</dd>
        <dt>Zdjęcia / dokumenty</dt><dd>@if ($complaint->drive_link)<a href="{{ $complaint->drive_link }}" target="_blank" rel="noopener noreferrer">otwórz</a>@else — @endif</dd>
        <dt>Gdzie jest produkt</dt><dd>{{ $complaint->product_location->label() }}</dd>
        @if ($complaint->service_name)<dt>Serwis</dt><dd>{{ $complaint->service_name }} · wysłano {{ $complaint->service_sent_on?->format('d.m.Y') ?? '—' }} · wróciło {{ $complaint->service_returned_on?->format('d.m.Y') ?? '—' }}</dd>@endif
        <dt>Odpowiedź dla klienta</dt><dd>@include('complaints.response', ['complaint' => $complaint])</dd>
        <dt>Rozstrzygnięcie</dt><dd>{{ $complaint->resolution?->label() ?? '—' }}@if ($complaint->resolved_on) · {{ $complaint->resolved_on->format('d.m.Y') }}@endif @if ($complaint->wz_number) · WZ {{ $complaint->wz_number }}@endif</dd>
        <dt>Prowadzi</dt><dd>{{ $complaint->assignee?->name ?? '—' }}</dd>
        <dt>Dodał(a)</dt><dd>{{ $complaint->author->name }} · {{ $complaint->created_at->format('d.m.Y H:i') }}</dd>
        @if ($complaint->closed_at)<dt>Zakończono</dt><dd>{{ $complaint->closed_at->format('d.m.Y H:i') }}</dd>@endif
        <dt>Uwagi</dt><dd style="white-space:pre-line">{{ $complaint->notes ?? '—' }}</dd>
    </dl>
</div>
<div class="row-actions" style="margin-bottom:16px">
    <a class="btn btn-primary" href="{{ route('complaints.edit', $complaint) }}">Edytuj</a>
    @if ($complaint->resolution === \App\Enums\ComplaintResolution::ZwrotPieniedzy)<a class="btn" href="{{ route('returns.create', ['zamowienie' => $order->id]) }}">Dodaj zwrot pieniędzy</a>@endif
    @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('complaints.destroy', $complaint) }}" onsubmit="return confirm('Usunąć reklamację razem z historią?')">@csrf @method('delete')<button class="btn btn-danger">Usuń</button></form>
    @endif
</div>

<h2>Historia zmian</h2>
<table>
    <thead><tr><th>Kiedy</th><th>Kto</th><th>Co</th></tr></thead>
    <tbody>
    @foreach ($complaint->events as $event)
        <tr>
            <td>{{ $event->created_at->format('d.m.Y H:i') }}</td>
            <td>{{ $event->user?->name ?? '—' }}</td>
            <td>
                @if ($event->action === 'utworzono')
                    Dodano reklamację
                @else
                    @foreach ($event->changes ?? [] as $field => [$old, $new])
                        <div>{{ \App\Models\OrderComplaint::TRACKED[$field] ?? $field }}: <span class="muted">{{ \Illuminate\Support\Str::limit($old ?? '—', 80) }}</span> → {{ \Illuminate\Support\Str::limit($new ?? '—', 80) }}</div>
                    @endforeach
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
