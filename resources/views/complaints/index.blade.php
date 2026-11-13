@extends('layouts.app')
@section('title', 'Reklamacje')
@section('content')
<h1>Reklamacje</h1>
<p><a class="btn btn-primary" href="{{ route('complaints.create') }}">Dodaj reklamację</a></p>
<form class="filters" method="get">
    <div><label for="q">Szukaj</label><input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nr zamówienia, EAN, produkt, klient, WZ"></div>
    <div><label for="gdzie">Gdzie jest produkt</label>
        <select id="gdzie" name="gdzie"><option value="">wszędzie</option>
            @foreach (\App\Enums\ProductLocation::cases() as $l)<option value="{{ $l->value }}" @selected(($filters['gdzie'] ?? '') === $l->value)>{{ $l->label() }}</option>@endforeach
        </select></div>
    <div><label for="status">Status</label>
        <select id="status" name="status">
            <option value="otwarte" @selected($filters['status'] === 'otwarte')>otwarte</option>
            <option value="bez_odpowiedzi" @selected($filters['status'] === 'bez_odpowiedzi')>bez odpowiedzi dla klienta</option>
            @foreach (\App\Enums\CaseStatus::cases() as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach
            <option value="wszystkie" @selected($filters['status'] === 'wszystkie')>wszystkie</option>
        </select></div>
    <div><button class="btn btn-primary">Filtruj</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>Nr</th><th>Zamówienie</th><th>Produkt</th><th>Gdzie jest</th><th>Odpowiedź</th><th>Rozstrzygnięcie</th><th>Status</th><th>Prowadzi</th></tr></thead>
    <tbody>
    @forelse ($complaints as $complaint)
        <tr>
            <td><a href="{{ route('complaints.show', $complaint) }}">{{ $complaint->id }}</a><div class="muted">{{ $complaint->reported_on->format('d.m.Y') }}</div></td>
            <td>{{ $complaint->order->baselinker_order_id }}<div class="muted">{{ $complaint->order->customer_name }}</div></td>
            <td>{{ $complaint->product_name }}<div class="muted">{{ $complaint->ean }}</div></td>
            <td>{{ $complaint->product_location->label() }}@if ($complaint->service_name)<div class="muted">{{ $complaint->service_name }}</div>@endif</td>
            <td>@include('complaints.response', ['complaint' => $complaint])</td>
            <td>{{ $complaint->resolution?->label() ?? '—' }}</td>
            <td><span class="badge">{{ $complaint->status->label() }}</span></td>
            <td>{{ $complaint->assignee?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Brak reklamacji.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="pagination">
    @if ($complaints->previousPageUrl())<a class="btn" href="{{ $complaints->previousPageUrl() }}">← Nowsze</a>@endif
    <span class="muted">{{ $complaints->total() }} reklamacji</span>
    @if ($complaints->nextPageUrl())<a class="btn" href="{{ $complaints->nextPageUrl() }}">Starsze →</a>@endif
</div>
@endsection
