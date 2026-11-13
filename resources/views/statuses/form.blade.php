@extends('layouts.app')
@section('title', $status->exists ? 'Edycja statusu' : 'Nowy status')
@section('content')
<h1>{{ $status->exists ? 'Edycja statusu' : 'Nowy status' }}</h1>
<form class="card" method="post" action="{{ $status->exists ? route('statuses.update', $status) : route('statuses.store') }}">
    @csrf
    @if ($status->exists) @method('put') @endif
    <label for="name">Nazwa statusu *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $status->name) }}" required>
    @error('name')<div class="error">{{ $message }}</div>@enderror

    <label for="group">Grupa *</label>
    <select id="group" name="group" required>
        @foreach (\App\Enums\StatusGroup::cases() as $group)
            <option value="{{ $group->value }}" @selected(old('group', $status->group?->value) === $group->value)>{{ $group->label() }}</option>
        @endforeach
    </select>
    @error('group')<div class="error">{{ $message }}</div>@enderror

    <label for="baselinker_status_id">ID statusu w BaseLinkerze</label>
    <input id="baselinker_status_id" type="number" name="baselinker_status_id" value="{{ old('baselinker_status_id', $status->baselinker_status_id) }}">
    @error('baselinker_status_id')<div class="error">{{ $message }}</div>@enderror

    <label for="notes">Uwagi</label>
    <input id="notes" type="text" name="notes" value="{{ old('notes', $status->notes) }}">
    @error('notes')<div class="error">{{ $message }}</div>@enderror

    <p><button class="btn btn-primary">Zapisz</button> <a class="btn" href="{{ route('statuses.index') }}">Anuluj</a></p>
</form>
@endsection
