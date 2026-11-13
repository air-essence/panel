@extends('layouts.app')
@section('title', 'Spółki')
@section('content')
<h1>Spółki</h1>
@if (auth()->user()->isAdmin())<p><a class="btn btn-primary" href="{{ route('companies.create') }}">Dodaj spółkę</a></p>@endif
<table>
    <thead><tr><th>Nazwa</th><th>Skrót</th><th>Kanały</th>@if (auth()->user()->isAdmin())<th></th>@endif</tr></thead>
    <tbody>
    @forelse ($companies as $company)
        <tr>
            <td>{{ $company->name }}</td>
            <td>{{ $company->short_name }}</td>
            <td>{{ $company->sales_channels_count }}</td>
            @if (auth()->user()->isAdmin())
            <td class="row-actions">
                <a class="btn" href="{{ route('companies.edit', $company) }}">Edytuj</a>
                <form method="post" action="{{ route('companies.destroy', $company) }}" onsubmit="return confirm('Usunąć spółkę?')">@csrf @method('delete')<button class="btn btn-danger">Usuń</button></form>
            </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="4" class="muted">Brak spółek.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
