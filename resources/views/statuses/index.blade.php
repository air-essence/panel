@extends('layouts.app')
@section('title', 'Statusy')
@section('content')
<h1>Statusy zamówień</h1>
<p class="muted">Każdy status ze sklepów i BaseLinkera należy do jednej grupy. Na grupach opierają się reguły wykrywania rozbieżności.</p>
@if (auth()->user()->isAdmin())<p><a class="btn btn-primary" href="{{ route('statuses.create') }}">Dodaj status</a></p>@endif
<table>
    <thead><tr><th>Status</th><th>Grupa</th><th>ID w BaseLinkerze</th><th>Uwagi</th>@if (auth()->user()->isAdmin())<th></th>@endif</tr></thead>
    <tbody>
    @forelse ($statuses as $status)
        <tr>
            <td>{{ $status->name }}</td>
            <td>{!! $status->group === \App\Enums\StatusGroup::DoUstalenia ? '<span class="badge badge-warn">'.e($status->group->label()).'</span>' : e($status->group->label()) !!}</td>
            <td>{!! $status->baselinker_status_id ?? '<span class="badge badge-warn">do uzupełnienia</span>' !!}</td>
            <td class="muted">{{ $status->notes }}</td>
            @if (auth()->user()->isAdmin())
            <td class="row-actions">
                <a class="btn" href="{{ route('statuses.edit', $status) }}">Edytuj</a>
                <form method="post" action="{{ route('statuses.destroy', $status) }}" onsubmit="return confirm('Usunąć status?')">@csrf @method('delete')<button class="btn btn-danger">Usuń</button></form>
            </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Brak statusów.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
