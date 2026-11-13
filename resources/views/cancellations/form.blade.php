@extends('layouts.app')
@section('title', $case->exists ? 'Edycja anulacji' : 'Nowa anulacja')
@section('content')
<h1>{{ $case->exists ? 'Anulacja nr '.$case->id : 'Zgłoś anulację' }}</h1>
<form class="card" method="post" action="{{ $case->exists ? route('cancellations.update', $case) : route('cancellations.store') }}">
    @csrf
    @if ($case->exists) @method('put') @endif

    @if ($case->exists)
        <p><strong>Zamówienie:</strong> <a href="{{ route('orders.show', $order) }}">{{ $order->baselinker_order_id }}</a> · {{ $order->customer_name }}@if ($order->salesChannel) · {{ $order->salesChannel->name }}@endif</p>
    @else
        <label for="order_number">Numer zamówienia *</label>
        <input id="order_number" type="text" name="order_number" value="{{ old('order_number', $order?->baselinker_order_id) }}" required placeholder="nr w BaseLinkerze, w sklepie albo z Allegro">
        @if ($order)<div class="muted">{{ $order->customer_name }}@if ($order->salesChannel) · {{ $order->salesChannel->name }}@endif · wpłacono {{ number_format((float) $order->paid, 2, ',', ' ') }} {{ $order->currency }}@if ($order->tracking_number) · przesyłka {{ $order->tracking_number }}@endif</div>@endif
        @error('order_number')<div class="error">{{ $message }}</div>@enderror
    @endif

    <label for="reason">Powód *</label>
    <select id="reason" name="reason" required>
        <option value="">— wybierz —</option>
        @foreach (\App\Enums\CancellationReason::cases() as $reason)
            <option value="{{ $reason->value }}" @selected(old('reason', $case->reason?->value) === $reason->value)>{{ $reason->label() }}</option>
        @endforeach
    </select>
    @error('reason')<div class="error">{{ $message }}</div>@enderror

    <label for="reason_details">Opis powodu <span class="muted">(wymagany przy „Inny”)</span></label>
    <input id="reason_details" type="text" name="reason_details" value="{{ old('reason_details', $case->reason_details) }}">
    @error('reason_details')<div class="error">{{ $message }}</div>@enderror

    <label for="requested_on">Data zgłoszenia anulacji *</label>
    <input id="requested_on" type="date" name="requested_on" value="{{ old('requested_on', $case->requested_on?->format('Y-m-d')) }}" required>
    @error('requested_on')<div class="error">{{ $message }}</div>@enderror

    <label for="shipment_hold">Co z przesyłką? *</label>
    <select id="shipment_hold" name="shipment_hold" required>
        <option value="">— wybierz —</option>
        @foreach (\App\Enums\ShipmentHold::cases() as $hold)
            <option value="{{ $hold->value }}" @selected(old('shipment_hold', $case->shipment_hold?->value) === $hold->value)>{{ $hold->label() }}</option>
        @endforeach
    </select>
    @error('shipment_hold')<div class="error">{{ $message }}</div>@enderror

    <label for="refund_status">Zwrot pieniędzy *</label>
    <select id="refund_status" name="refund_status" required>
        <option value="">— wybierz —</option>
        @foreach (\App\Enums\RefundStatus::cases() as $refund)
            <option value="{{ $refund->value }}" @selected(old('refund_status', $case->refund_status?->value) === $refund->value)>{{ $refund->label() }}</option>
        @endforeach
    </select>
    @error('refund_status')<div class="error">{{ $message }}</div>@enderror

    <label for="refund_amount">Kwota zwrotu <span class="muted">(wymagana, jeśli jest zwrot)</span></label>
    <input id="refund_amount" type="number" step="0.01" min="0" name="refund_amount" value="{{ old('refund_amount', $case->refund_amount ?? ($order && ! $case->exists && (float) $order->paid > 0 ? $order->paid : null)) }}">
    @error('refund_amount')<div class="error">{{ $message }}</div>@enderror

    <label for="refunded_on">Data zwrotu <span class="muted">(wymagana przy „Zwrócono”)</span></label>
    <input id="refunded_on" type="date" name="refunded_on" value="{{ old('refunded_on', $case->refunded_on?->format('Y-m-d')) }}">
    @error('refunded_on')<div class="error">{{ $message }}</div>@enderror

    <label for="assigned_to">Osoba prowadząca</label>
    <select id="assigned_to" name="assigned_to">
        <option value="">— nikt —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected((string) old('assigned_to', $case->assigned_to) === (string) $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>
    @error('assigned_to')<div class="error">{{ $message }}</div>@enderror

    @if ($case->exists)
        <label for="status">Status sprawy *</label>
        <select id="status" name="status" required>
            @foreach (\App\Enums\CaseStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('status', $case->status?->value) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        @error('status')<div class="error">{{ $message }}</div>@enderror
    @endif

    <label for="notes">Uwagi</label>
    <textarea id="notes" name="notes" rows="4" style="width:100%;max-width:620px;font:inherit;padding:7px 10px;border:1px solid var(--line);border-radius:6px">{{ old('notes', $case->notes) }}</textarea>
    @error('notes')<div class="error">{{ $message }}</div>@enderror

    <p><button class="btn btn-primary">Zapisz</button> <a class="btn" href="{{ $case->exists ? route('cancellations.show', $case) : route('cancellations.index') }}">Anuluj</a></p>
</form>
@endsection
