@extends('layouts.app')
@section('title', $complaint->exists ? 'Edycja reklamacji' : 'Nowa reklamacja')
@section('content')
@php($select = function (string $name, string $enum, $current) { return collect($enum::cases())->map(fn ($c) => '<option value="'.e($c->value).'"'.(old($name, $current?->value ?? $current) === $c->value ? ' selected' : '').'>'.e($c->label()).'</option>')->implode(''); })
@php($date = fn (string $name) => old($name, $complaint->{$name}?->format('Y-m-d')))
<h1>{{ $complaint->exists ? 'Reklamacja nr '.$complaint->id : 'Dodaj reklamację' }}</h1>
<form class="card" method="post" action="{{ $complaint->exists ? route('complaints.update', $complaint) : route('complaints.store') }}">
    @csrf
    @if ($complaint->exists) @method('put') @endif

    @if ($complaint->exists)
        <p><strong>Zamówienie:</strong> <a href="{{ route('orders.show', $order) }}">{{ $order->baselinker_order_id }}</a> · {{ $order->customer_name }}</p>
    @else
        <label for="order_number">Numer zamówienia *</label>
        <input id="order_number" type="text" name="order_number" value="{{ old('order_number', $order?->baselinker_order_id) }}" required placeholder="nr w BaseLinkerze, w sklepie albo z Allegro">
        @if ($order)<div class="muted">{{ $order->customer_name }} · {{ $order->ordered_at?->format('d.m.Y') }}</div>@endif
        @error('order_number')<div class="error">{{ $message }}</div>@enderror
    @endif

    <h3>Produkt i wada</h3>
    @if ($order && $order->items->isNotEmpty())
        <label for="order_item_id">Produkt z zamówienia</label>
        <select id="order_item_id" name="order_item_id">
            <option value="">— wpiszę ręcznie —</option>
            @foreach ($order->items as $item)
                <option value="{{ $item->id }}" @selected((string) old('order_item_id') === (string) $item->id)>{{ $item->name }}{{ $item->ean ? ' · EAN '.$item->ean : '' }}</option>
            @endforeach
        </select>
        @error('order_item_id')<div class="error">{{ $message }}</div>@enderror
    @endif

    <label for="product_name">Nazwa produktu <span class="muted">{{ $order && $order->items->isNotEmpty() ? '(gdy nie wybrano z listy)' : '*' }}</span></label>
    <input id="product_name" type="text" name="product_name" value="{{ old('product_name', $complaint->product_name) }}">
    @error('product_name')<div class="error">{{ $message }}</div>@enderror

    <label for="ean">EAN</label>
    <input id="ean" type="text" name="ean" value="{{ old('ean', $complaint->ean) }}">
    @error('ean')<div class="error">{{ $message }}</div>@enderror

    <label for="reported_on">Data zgłoszenia reklamacji *</label>
    <input id="reported_on" type="date" name="reported_on" value="{{ $date('reported_on') }}" required>
    <div class="muted">Na odpowiedź klientowi jest 14 dni od tej daty.</div>
    @error('reported_on')<div class="error">{{ $message }}</div>@enderror

    <label for="defect_description">Opis wady *</label>
    <textarea id="defect_description" name="defect_description" rows="3" required style="width:100%;max-width:620px;font:inherit;padding:7px 10px;border:1px solid var(--line);border-radius:6px">{{ old('defect_description', $complaint->defect_description) }}</textarea>
    @error('defect_description')<div class="error">{{ $message }}</div>@enderror

    <label for="drive_link">Link do zdjęć / dokumentów (dysk)</label>
    <input id="drive_link" type="text" name="drive_link" value="{{ old('drive_link', $complaint->drive_link) }}" placeholder="https://drive.google.com/…">
    @error('drive_link')<div class="error">{{ $message }}</div>@enderror

    <h3>Gdzie jest produkt</h3>
    <label for="product_location">Co dzieje się z produktem? *</label>
    <select id="product_location" name="product_location" required><option value="">— wybierz —</option>{!! $select('product_location', \App\Enums\ProductLocation::class, $complaint->product_location) !!}</select>
    @error('product_location')<div class="error">{{ $message }}</div>@enderror

    <label for="service_name">Serwis <span class="muted">(wymagany, gdy produkt jest w serwisie)</span></label>
    <input id="service_name" type="text" name="service_name" list="services" value="{{ old('service_name', $complaint->service_name) }}">
    <datalist id="services"><option value="RG Media"><option value="EXTRA"></datalist>
    @error('service_name')<div class="error">{{ $message }}</div>@enderror

    <label for="service_sent_on">Wysłano do serwisu</label>
    <input id="service_sent_on" type="date" name="service_sent_on" value="{{ $date('service_sent_on') }}">
    @error('service_sent_on')<div class="error">{{ $message }}</div>@enderror

    <label for="service_returned_on">Wróciło z serwisu</label>
    <input id="service_returned_on" type="date" name="service_returned_on" value="{{ $date('service_returned_on') }}">
    @error('service_returned_on')<div class="error">{{ $message }}</div>@enderror

    <h3>Odpowiedź i rozstrzygnięcie</h3>
    <label for="responded_on">Data odpowiedzi dla klienta</label>
    <input id="responded_on" type="date" name="responded_on" value="{{ $date('responded_on') }}">
    @error('responded_on')<div class="error">{{ $message }}</div>@enderror

    <label for="resolution">Rozstrzygnięcie</label>
    <select id="resolution" name="resolution"><option value="">— jeszcze nie —</option>{!! $select('resolution', \App\Enums\ComplaintResolution::class, $complaint->resolution) !!}</select>
    @error('resolution')<div class="error">{{ $message }}</div>@enderror

    <label for="resolved_on">Data rozstrzygnięcia <span class="muted">(wymagana przy rozstrzygnięciu)</span></label>
    <input id="resolved_on" type="date" name="resolved_on" value="{{ $date('resolved_on') }}">
    @error('resolved_on')<div class="error">{{ $message }}</div>@enderror

    <label for="wz_number">Numer WZ <span class="muted">(wymagany przy nowym produkcie)</span></label>
    <input id="wz_number" type="text" name="wz_number" value="{{ old('wz_number', $complaint->wz_number) }}">
    @error('wz_number')<div class="error">{{ $message }}</div>@enderror

    <h3>Obsługa</h3>
    <label for="assigned_to">Osoba prowadząca</label>
    <select id="assigned_to" name="assigned_to">
        <option value="">— nikt —</option>
        @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) old('assigned_to', $complaint->assigned_to) === (string) $user->id)>{{ $user->name }}</option>@endforeach
    </select>

    @if ($complaint->exists)
        <label for="status">Status sprawy *</label>
        <select id="status" name="status" required>{!! $select('status', \App\Enums\CaseStatus::class, $complaint->status) !!}</select>
        @error('status')<div class="error">{{ $message }}</div>@enderror
    @endif

    <label for="notes">Uwagi</label>
    <textarea id="notes" name="notes" rows="3" style="width:100%;max-width:620px;font:inherit;padding:7px 10px;border:1px solid var(--line);border-radius:6px">{{ old('notes', $complaint->notes) }}</textarea>

    <p><button class="btn btn-primary">Zapisz</button> <a class="btn" href="{{ $complaint->exists ? route('complaints.show', $complaint) : route('complaints.index') }}">Anuluj</a></p>
</form>
@endsection
