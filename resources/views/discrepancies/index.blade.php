@extends('layouts.app')
@section('title', 'Do wyjaśnienia')
@section('content')
<h1>Do wyjaśnienia</h1>
<p class="muted">Miejsca, w których sprawy w panelu nie zgadzają się z zamówieniami w BaseLinkerze. Rozbieżność znika sama, gdy dane się zgodzą, albo po oznaczeniu jej jako wyjaśnionej z krótkim opisem.</p>
<form class="filters" method="get">
    <div><label for="waga">Waga</label>
        <select id="waga" name="waga"><option value="">wszystkie</option>
            @foreach (\App\Enums\Severity::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['waga'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
        </select></div>
    <div><label for="regula">Reguła</label>
        <select id="regula" name="regula"><option value="">wszystkie</option>
            @foreach (\App\Services\Discrepancies\DiscrepancyFinder::RULES as $key => $label)<option value="{{ $key }}" @selected(($filters['regula'] ?? '') === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>@endforeach
        </select></div>
    <div><button class="btn btn-primary">Filtruj</button></div>
</form>

@if ($errors->any())<div class="error" style="margin-bottom:12px">{{ $errors->first() }}</div>@endif

@forelse ($discrepancies as $d)
    @include('discrepancies.item', ['d' => $d])
@empty
    <div class="card muted">{{ $total ? 'Brak rozbieżności dla wybranych filtrów.' : 'Brak rozbieżności. Dane w panelu zgadzają się z BaseLinkerem.' }}</div>
@endforelse

<h2>Ostatnio wyjaśnione</h2>
<table>
    <thead><tr><th>Kiedy</th><th>Kto</th><th>Zamówienie</th><th>Reguła</th><th>Wyjaśnienie</th><th></th></tr></thead>
    <tbody>
    @forelse ($dismissals as $dismissal)
        <tr>
            <td>{{ $dismissal->created_at->format('d.m.Y H:i') }}</td>
            <td>{{ $dismissal->user?->name ?? '—' }}</td>
            <td><a href="{{ route('orders.show', $dismissal->order) }}">{{ $dismissal->order->baselinker_order_id }}</a></td>
            <td>{{ \App\Services\Discrepancies\DiscrepancyFinder::RULES[$dismissal->rule] ?? $dismissal->rule }}</td>
            <td>{{ $dismissal->note }}</td>
            <td><form method="post" action="{{ route('discrepancies.restore', $dismissal) }}">@csrf @method('delete')<button class="btn">Przywróć</button></form></td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Nic jeszcze nie oznaczono.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
