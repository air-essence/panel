@extends('layouts.app')
@section('title', 'Zamówienia')
@section('content')
<h1>Zamówienia</h1>
<p class="muted">Dane pobierane z BaseLinkera co 5 minut. Panel ich nie zmienia.</p>
<form class="filters" method="get">
    <div><label for="q">Szukaj</label><input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nr zamówienia, przesyłki, e-mail, nazwisko"></div>
    <div><label for="kanal">Kanał</label>
        <select id="kanal" name="kanal"><option value="">wszystkie</option>
            @foreach ($channels as $channel)<option value="{{ $channel->id }}" @selected((string) ($filters['kanal'] ?? '') === (string) $channel->id)>{{ $channel->name }}</option>@endforeach
        </select></div>
    <div><label for="grupa">Grupa statusu</label>
        <select id="grupa" name="grupa"><option value="">wszystkie</option>
            @foreach (\App\Enums\StatusGroup::cases() as $group)<option value="{{ $group->value }}" @selected(($filters['grupa'] ?? '') === $group->value)>{{ $group->label() }}</option>@endforeach
        </select></div>
    <div><button class="btn btn-primary">Filtruj</button> <a class="btn" href="{{ route('orders.index') }}">Wyczyść</a></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>Nr BL</th><th>Data</th><th>Kanał</th><th>Klient</th><th>Status</th><th>Kwota</th><th>Płatność</th><th>Przesyłka</th></tr></thead>
    <tbody>
    @forelse ($orders as $order)
        <tr>
            <td><a href="{{ route('orders.show', $order) }}">{{ $order->baselinker_order_id }}</a>@if ($order->shop_order_id)<div class="muted">sklep: {{ $order->shop_order_id }}</div>@endif</td>
            <td>{{ $order->ordered_at?->format('d.m.Y H:i') }}</td>
            <td>{!! $order->salesChannel ? e($order->salesChannel->name) : '<span class="badge badge-warn">bez kanału</span>' !!}</td>
            <td>{{ $order->customer_name }}<div class="muted">{{ $order->email }}</div></td>
            <td>@include('orders.status', ['order' => $order])</td>
            <td>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</td>
            <td>@include('orders.payment', ['order' => $order])</td>
            <td>{{ $order->tracking_number ?? '—' }}<div class="muted">{{ $order->carrier }}</div></td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Brak zamówień. Pojawią się po synchronizacji z BaseLinkerem.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="pagination">
    @if ($orders->previousPageUrl())<a class="btn" href="{{ $orders->previousPageUrl() }}">← Nowsze</a>@endif
    <span class="muted">Strona {{ $orders->currentPage() }} z {{ $orders->lastPage() }} · {{ $orders->total() }} zamówień</span>
    @if ($orders->nextPageUrl())<a class="btn" href="{{ $orders->nextPageUrl() }}">Starsze →</a>@endif
</div>
@endsection
