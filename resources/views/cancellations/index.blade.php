@extends('layouts.app')
@section('title', 'Anulacje')
@section('content')
<h1>Anulacje</h1>
<p><a class="btn btn-primary" href="{{ route('cancellations.create') }}">Zgłoś anulację</a></p>
<form class="filters" method="get">
    <div><label for="q">Szukaj</label><input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nr zamówienia, klient, e-mail"></div>
    <div><label for="status">Status</label>
        <select id="status" name="status">
            <option value="otwarte" @selected($filters['status'] === 'otwarte')>otwarte</option>
            @foreach (\App\Enums\CaseStatus::cases() as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach
            <option value="wszystkie" @selected($filters['status'] === 'wszystkie')>wszystkie</option>
        </select></div>
    <div><button class="btn btn-primary">Filtruj</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>Nr</th><th>Zamówienie</th><th>Klient</th><th>Powód</th><th>Zwrot pieniędzy</th><th>Przesyłka</th><th>Status</th><th>Prowadzi</th><th>Uwagi</th></tr></thead>
    <tbody>
    @forelse ($cases as $case)
        @php($warnings = $case->warnings())
        <tr>
            <td><a href="{{ route('cancellations.show', $case) }}">{{ $case->id }}</a><div class="muted">{{ $case->requested_on->format('d.m.Y') }}</div></td>
            <td>{{ $case->order->baselinker_order_id }}<div class="muted">{{ $case->order->salesChannel?->name }}</div></td>
            <td>{{ $case->order->customer_name }}</td>
            <td>{{ $case->reason->label() }}</td>
            <td>@include('cancellations.refund', ['case' => $case])</td>
            <td>{{ $case->shipment_hold->label() }}</td>
            <td><span class="badge">{{ $case->status->label() }}</span></td>
            <td>{{ $case->assignee?->name ?? '—' }}</td>
            <td>@if ($warnings)<span class="badge badge-danger" title="{{ implode(' ', $warnings) }}">{{ count($warnings) }} {{ count($warnings) === 1 ? 'ostrzeżenie' : 'ostrzeżenia' }}</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="9" class="muted">Brak anulacji.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="pagination">
    @if ($cases->previousPageUrl())<a class="btn" href="{{ $cases->previousPageUrl() }}">← Nowsze</a>@endif
    <span class="muted">{{ $cases->total() }} spraw</span>
    @if ($cases->nextPageUrl())<a class="btn" href="{{ $cases->nextPageUrl() }}">Starsze →</a>@endif
</div>
@endsection
