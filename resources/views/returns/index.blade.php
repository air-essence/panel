@extends('layouts.app')
@section('title', 'Zwroty')
@section('content')
<h1>Zwroty</h1>
<p><a class="btn btn-primary" href="{{ route('returns.create') }}">Dodaj zwrot</a></p>
<form class="filters" method="get">
    <div><label for="q">Szukaj</label><input id="q" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="nr zamówienia, dokumentu, klient"></div>
    <div><label for="spolka">Spółka</label>
        <select id="spolka" name="spolka"><option value="">wszystkie</option>
            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected((string) ($filters['spolka'] ?? '') === (string) $company->id)>{{ $company->name }}</option>@endforeach
        </select></div>
    <div><label for="status">Status</label>
        <select id="status" name="status">
            <option value="otwarte" @selected($filters['status'] === 'otwarte')>otwarte</option>
            <option value="po_terminie" @selected($filters['status'] === 'po_terminie')>po terminie zwrotu pieniędzy</option>
            @foreach (\App\Enums\CaseStatus::cases() as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach
            <option value="wszystkie" @selected($filters['status'] === 'wszystkie')>wszystkie</option>
        </select></div>
    <div><button class="btn btn-primary">Filtruj</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>Nr</th><th>Zamówienie</th><th>Spółka</th><th>Rodzaj</th><th>Towar</th><th>Kwota</th><th>Pieniądze</th><th>Dokumenty</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($returns as $return)
        <tr>
            <td><a href="{{ route('returns.show', $return) }}">{{ $return->id }}</a><div class="muted">{{ $return->requested_on->format('d.m.Y') }}</div></td>
            <td>{{ $return->order->baselinker_order_id }}<div class="muted">{{ $return->order->customer_name }}</div></td>
            <td>{{ $return->order->salesChannel?->company?->short_name ?? '—' }}</td>
            <td>{{ $return->kind->label() }}</td>
            <td>{{ $return->goods_status->label() }}@if ($return->goods_location)<div class="muted">{{ $return->goods_location }}</div>@endif</td>
            <td>{{ number_format((float) $return->amount, 2, ',', ' ') }} {{ $return->order->currency }}</td>
            <td>@include('returns.refund', ['return' => $return])</td>
            <td>{{ $return->sale_document->label() }} {{ $return->sale_document_number }}<div class="muted">{{ $return->correction_document_number ? 'korekta/PZ: '.$return->correction_document_number : 'brak korekty/PZ' }}</div></td>
            <td><span class="badge">{{ $return->status->label() }}</span></td>
        </tr>
    @empty
        <tr><td colspan="9" class="muted">Brak zwrotów.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div class="pagination">
    @if ($returns->previousPageUrl())<a class="btn" href="{{ $returns->previousPageUrl() }}">← Nowsze</a>@endif
    <span class="muted">{{ $returns->total() }} zwrotów</span>
    @if ($returns->nextPageUrl())<a class="btn" href="{{ $returns->nextPageUrl() }}">Starsze →</a>@endif
</div>
@endsection
