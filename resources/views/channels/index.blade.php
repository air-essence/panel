@extends('layouts.app')
@section('title', 'Kanały sprzedaży')
@section('content')
<h1>Kanały sprzedaży</h1>
@if (auth()->user()->isAdmin())<p><a class="btn btn-primary" href="{{ route('channels.create') }}">Dodaj kanał</a></p>@endif
<table>
    <thead><tr><th>Nazwa</th><th>Typ</th><th>Spółka</th><th>Źródło w BaseLinkerze</th><th>Aktywny</th>@if (auth()->user()->isAdmin())<th></th>@endif</tr></thead>
    <tbody>
    @forelse ($channels as $channel)
        <tr>
            <td>{{ $channel->name }}</td>
            <td>{{ $channel->type->label() }}</td>
            <td>{!! $channel->company ? e($channel->company->short_name) : '<span class="badge badge-warn">do przypisania</span>' !!}</td>
            <td>{!! $channel->baselinker_source ? e($channel->baselinker_source) : '<span class="badge badge-warn">do uzupełnienia</span>' !!}</td>
            <td>{{ $channel->is_active ? 'tak' : 'nie' }}</td>
            @if (auth()->user()->isAdmin())
            <td class="row-actions">
                <a class="btn" href="{{ route('channels.edit', $channel) }}">Edytuj</a>
                <form method="post" action="{{ route('channels.destroy', $channel) }}" onsubmit="return confirm('Usunąć kanał?')">@csrf @method('delete')<button class="btn btn-danger">Usuń</button></form>
            </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Brak kanałów.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
