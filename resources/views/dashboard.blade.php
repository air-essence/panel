@extends('layouts.app')
@section('title', 'Pulpit')
@section('content')
<h1>Pulpit</h1>
<div class="stats">
    <div class="card stat"><span class="muted">Spółki</span><strong>{{ $companies }}</strong></div>
    <div class="card stat"><span class="muted">Kanały sprzedaży</span><strong>{{ $channels }}</strong></div>
    <div class="card stat"><span class="muted">Statusy</span><strong>{{ $statuses }}</strong></div>
    <div class="card stat"><span class="muted">Statusy bez powiązania z BaseLinkerem</span><strong>{{ $unmappedStatuses }}</strong></div>
</div>
<div class="card">
    <h2 style="margin-top:0">Do wyjaśnienia</h2>
    <p class="muted">Tu pojawią się rozbieżności z BaseLinkerem, gdy podłączymy synchronizację (krok 3 planu MVP).</p>
</div>
@endsection
