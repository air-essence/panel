@extends('layouts.app')
@section('title', $return->exists ? 'Edycja zwrotu' : 'Nowy zwrot')
@section('content')
@php($select = function (string $name, string $enum, $current) { return collect($enum::cases())->map(fn ($c) => '<option value="'.e($c->value).'"'.(old($name, $current?->value ?? $current) === $c->value ? ' selected' : '').'>'.e($c->label()).'</option>')->implode(''); })
<h1>{{ $return->exists ? 'Zwrot nr '.$return->id : 'Dodaj zwrot' }}</h1>
<form class="card" method="post" action="{{ $return->exists ? route('returns.update', $return) : route('returns.store') }}">
    @csrf
    @if ($return->exists) @method('put') @endif

    @if ($return->exists)
        <p><strong>Zamówienie:</strong> <a href="{{ route('orders.show', $order) }}">{{ $order->baselinker_order_id }}</a> · {{ $order->customer_name }}@if ($order->salesChannel?->company) · {{ $order->salesChannel->company->name }}@endif</p>
    @else
        <label for="order_number">Numer zamówienia *</label>
        <input id="order_number" type="text" name="order_number" value="{{ old('order_number', $order?->baselinker_order_id) }}" required placeholder="nr w BaseLinkerze, w sklepie albo z Allegro">
        @if ($order)<div class="muted">{{ $order->customer_name }}@if ($order->salesChannel) · {{ $order->salesChannel->name }}@endif @if ($order->salesChannel?->company) · {{ $order->salesChannel->company->name }}@endif · wpłacono {{ number_format((float) $order->paid, 2, ',', ' ') }} {{ $order->currency }} · {{ $order->payment_method }}</div>@endif
        @error('order_number')<div class="error">{{ $message }}</div>@enderror
    @endif

    <h3>Zgłoszenie</h3>
    <label for="kind">Rodzaj *</label>
    <select id="kind" name="kind" required><option value="">— wybierz —</option>{!! $select('kind', \App\Enums\ReturnKind::class, $return->kind) !!}</select>
    @error('kind')<div class="error">{{ $message }}</div>@enderror

    <label for="requested_on">Data zgłoszenia przez klienta *</label>
    <input id="requested_on" type="date" name="requested_on" value="{{ old('requested_on', $return->requested_on?->format('Y-m-d')) }}" required>
    <div class="muted">Przy odstąpieniu od umowy pieniądze trzeba zwrócić w ciągu 14 dni od tej daty.</div>
    @error('requested_on')<div class="error">{{ $message }}</div>@enderror

    <h3>Towar</h3>
    <label for="goods_status">Stan towaru *</label>
    <select id="goods_status" name="goods_status" required><option value="">— wybierz —</option>{!! $select('goods_status', \App\Enums\GoodsStatus::class, $return->goods_status) !!}</select>
    @error('goods_status')<div class="error">{{ $message }}</div>@enderror

    <label for="return_tracking_number">Numer przesyłki zwrotnej</label>
    <input id="return_tracking_number" type="text" name="return_tracking_number" value="{{ old('return_tracking_number', $return->return_tracking_number) }}">
    @error('return_tracking_number')<div class="error">{{ $message }}</div>@enderror

    <label for="received_on">Data otrzymania towaru <span class="muted">(wymagana, gdy dotarł)</span></label>
    <input id="received_on" type="date" name="received_on" value="{{ old('received_on', $return->received_on?->format('Y-m-d')) }}">
    @error('received_on')<div class="error">{{ $message }}</div>@enderror

    <label for="goods_location">Gdzie jest towar? <span class="muted">(wymagane, gdy dotarł)</span></label>
    <input id="goods_location" type="text" name="goods_location" list="locations" value="{{ old('goods_location', $return->goods_location) }}" placeholder="np. Kalwaryjska">
    <datalist id="locations"><option value="Kalwaryjska"><option value="Kraków"><option value="Zielona Góra"><option value="Serwis"></datalist>
    @error('goods_location')<div class="error">{{ $message }}</div>@enderror

    <h3>Pieniądze</h3>
    <label for="amount">Kwota zwrotu *</label>
    <input id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $return->amount) }}" required>
    @error('amount')<div class="error">{{ $message }}</div>@enderror

    <label for="refund_method">Metoda zwrotu *</label>
    <select id="refund_method" name="refund_method" required><option value="">— wybierz —</option>{!! $select('refund_method', \App\Enums\RefundMethod::class, $return->refund_method) !!}</select>
    @error('refund_method')<div class="error">{{ $message }}</div>@enderror

    <label for="refund_account">Numer konta klienta <span class="muted">(wymagany przy przelewie)</span></label>
    <input id="refund_account" type="text" name="refund_account" value="{{ old('refund_account', $return->refund_account) }}" placeholder="26 cyfr">
    @error('refund_account')<div class="error">{{ $message }}</div>@enderror

    <label for="refunded_on">Data zwrotu pieniędzy</label>
    <input id="refunded_on" type="date" name="refunded_on" value="{{ old('refunded_on', $return->refunded_on?->format('Y-m-d')) }}">
    @error('refunded_on')<div class="error">{{ $message }}</div>@enderror

    <h3>Dokumenty</h3>
    <label for="sale_document">Dokument sprzedaży *</label>
    <select id="sale_document" name="sale_document" required><option value="">— wybierz —</option>{!! $select('sale_document', \App\Enums\SaleDocument::class, $return->sale_document) !!}</select>
    @error('sale_document')<div class="error">{{ $message }}</div>@enderror

    <label for="sale_document_number">Numer paragonu / faktury *</label>
    <input id="sale_document_number" type="text" name="sale_document_number" value="{{ old('sale_document_number', $return->sale_document_number) }}" required>
    @error('sale_document_number')<div class="error">{{ $message }}</div>@enderror

    <label for="correction_document_number">Numer korekty albo PZ <span class="muted">(wymagany do zakończenia)</span></label>
    <input id="correction_document_number" type="text" name="correction_document_number" value="{{ old('correction_document_number', $return->correction_document_number) }}">
    @error('correction_document_number')<div class="error">{{ $message }}</div>@enderror

    <h3>Obsługa</h3>
    <label for="assigned_to">Osoba prowadząca</label>
    <select id="assigned_to" name="assigned_to">
        <option value="">— nikt —</option>
        @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) old('assigned_to', $return->assigned_to) === (string) $user->id)>{{ $user->name }}</option>@endforeach
    </select>

    @if ($return->exists)
        <label for="status">Status sprawy *</label>
        <select id="status" name="status" required>{!! $select('status', \App\Enums\CaseStatus::class, $return->status) !!}</select>
        @error('status')<div class="error">{{ $message }}</div>@enderror
    @endif

    <label for="notes">Uwagi</label>
    <textarea id="notes" name="notes" rows="4" style="width:100%;max-width:620px;font:inherit;padding:7px 10px;border:1px solid var(--line);border-radius:6px">{{ old('notes', $return->notes) }}</textarea>

    <p><button class="btn btn-primary">Zapisz</button> <a class="btn" href="{{ $return->exists ? route('returns.show', $return) : route('returns.index') }}">Anuluj</a></p>
</form>
@endsection
