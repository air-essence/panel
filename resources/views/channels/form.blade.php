@extends('layouts.app')
@section('title', $channel->exists ? 'Edycja kanału' : 'Nowy kanał')
@section('content')
<h1>{{ $channel->exists ? 'Edycja kanału' : 'Nowy kanał sprzedaży' }}</h1>
<form class="card" method="post" action="{{ $channel->exists ? route('channels.update', $channel) : route('channels.store') }}">
    @csrf
    @if ($channel->exists) @method('put') @endif
    <label for="name">Nazwa *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $channel->name) }}" required>
    @error('name')<div class="error">{{ $message }}</div>@enderror

    <label for="type">Typ *</label>
    <select id="type" name="type" required>
        @foreach (\App\Enums\ChannelType::cases() as $type)
            <option value="{{ $type->value }}" @selected(old('type', $channel->type?->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </select>
    @error('type')<div class="error">{{ $message }}</div>@enderror

    <label for="company_id">Spółka</label>
    <select id="company_id" name="company_id">
        <option value="">— nie przypisano —</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((string) old('company_id', $channel->company_id) === (string) $company->id)>{{ $company->name }}</option>
        @endforeach
    </select>
    @error('company_id')<div class="error">{{ $message }}</div>@enderror

    <label for="baselinker_source">Źródło w BaseLinkerze</label>
    @if ($sources->isNotEmpty())
        <select id="baselinker_source" name="baselinker_source">
            <option value="">— nie przypisano —</option>
            @foreach ($sources as $source)
                <option value="{{ $source->key }}" @selected(old('baselinker_source', $channel->baselinker_source) === $source->key)>{{ $source->label() }}</option>
            @endforeach
        </select>
        <div class="muted">Zamówienia z wybranego źródła trafią do tego kanału.</div>
    @else
        <input id="baselinker_source" type="text" name="baselinker_source" value="{{ old('baselinker_source', $channel->baselinker_source) }}">
        <div class="muted">Lista źródeł pojawi się po pierwszej synchronizacji z BaseLinkerem.</div>
    @endif
    @error('baselinker_source')<div class="error">{{ $message }}</div>@enderror

    <label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $channel->is_active))> Kanał aktywny</label>

    <p><button class="btn btn-primary">Zapisz</button> <a class="btn" href="{{ route('channels.index') }}">Anuluj</a></p>
</form>
@endsection
