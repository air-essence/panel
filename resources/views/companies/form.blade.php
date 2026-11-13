@extends('layouts.app')
@section('title', $company->exists ? 'Edycja spółki' : 'Nowa spółka')
@section('content')
<h1>{{ $company->exists ? 'Edycja spółki' : 'Nowa spółka' }}</h1>
<form class="card" method="post" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}">
    @csrf
    @if ($company->exists) @method('put') @endif
    <label for="name">Pełna nazwa *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $company->name) }}" required>
    @error('name')<div class="error">{{ $message }}</div>@enderror

    <label for="short_name">Skrót *</label>
    <input id="short_name" type="text" name="short_name" value="{{ old('short_name', $company->short_name) }}" required>
    @error('short_name')<div class="error">{{ $message }}</div>@enderror

    <p><button class="btn btn-primary">Zapisz</button> <a class="btn" href="{{ route('companies.index') }}">Anuluj</a></p>
</form>
@endsection
