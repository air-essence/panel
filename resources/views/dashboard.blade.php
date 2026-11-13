@extends('layouts.app')
@section('title', 'Pulpit')
@section('content')
<h1>Pulpit</h1>
@if (! $tokenConfigured)
<div class="card"><strong>BaseLinker nie jest podłączony.</strong> <span class="muted">Ustaw BASELINKER_TOKEN w pliku .env na serwerze, a zamówienia zaczną się pobierać co 5 minut.</span></div>
@elseif ($lastSync)
<div class="card">
    <strong>Ostatnia synchronizacja z BaseLinkerem:</strong> {{ $lastSync->started_at->format('d.m.Y H:i') }}
    @if ($lastSync->succeeded())
        <span class="badge badge-ok">udana</span> <span class="muted">nowe: {{ $lastSync->orders_created }}, zaktualizowane: {{ $lastSync->orders_updated }}</span>
    @elseif ($lastSync->finished_at)
        <span class="badge badge-danger">błąd</span> <span class="muted">{{ $lastSync->error }}</span>
    @else
        <span class="badge">w toku</span>
    @endif
</div>
@else
<div class="card muted">Token BaseLinkera jest ustawiony. Pierwsza synchronizacja uruchomi się w ciągu 5 minut albo ręcznie: <code>php artisan panel:synchronizuj-baselinker</code>.</div>
@endif
<div class="stats">
    <div class="card stat"><span class="muted">Zamówienia w panelu</span><strong><a href="{{ route('orders.index') }}">{{ $orders }}</a></strong></div>
    <div class="card stat"><span class="muted">Otwarte anulacje</span><strong><a href="{{ route('cancellations.index') }}">{{ $openCancellations }}</a></strong></div>
    <div class="card stat"><span class="muted">Zamówienia bez kanału</span><strong>{{ $ordersWithoutChannel }}</strong></div>
    <div class="card stat"><span class="muted">Spółki</span><strong>{{ $companies }}</strong></div>
    <div class="card stat"><span class="muted">Kanały sprzedaży</span><strong>{{ $channels }}</strong></div>
    <div class="card stat"><span class="muted">Statusy</span><strong>{{ $statuses }}</strong></div>
    <div class="card stat"><span class="muted">Statusy bez powiązania z BaseLinkerem</span><strong>{{ $unmappedStatuses }}</strong></div>
</div>
@if ($unassignedSources->isNotEmpty())
<div class="card">
    <h2 style="margin-top:0">Źródła z BaseLinkera bez kanału</h2>
    <p class="muted">Przypisz każde źródło do kanału sprzedaży, aby zamówienia były poprawnie przypisane do spółki.</p>
    <ul>
        @foreach ($unassignedSources as $source)<li>{{ $source->label() }}</li>@endforeach
    </ul>
    @if (auth()->user()->isAdmin())<a class="btn" href="{{ route('channels.index') }}">Przejdź do kanałów</a>@endif
</div>
@endif
<div class="card">
    <h2 style="margin-top:0">Do wyjaśnienia @if ($discrepancyCount)<span class="badge badge-danger">{{ $discrepancyCount }}</span>@endif</h2>
    @forelse ($discrepancies as $d)
        @include('discrepancies.item', ['d' => $d, 'compact' => true])
    @empty
        <p class="muted">Brak rozbieżności między panelem a BaseLinkerem.</p>
    @endforelse
    @if ($discrepancyCount > $discrepancies->count())<p><a class="btn" href="{{ route('discrepancies.index') }}">Pokaż wszystkie ({{ $discrepancyCount }})</a></p>@endif
</div>
@endsection
