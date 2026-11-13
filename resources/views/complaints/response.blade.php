@if ($complaint->responded_on)
    <span class="badge badge-ok">udzielona</span><div class="muted">{{ $complaint->responded_on->format('d.m.Y') }}</div>
@elseif ($complaint->isResponseOverdue())
    <span class="badge badge-danger">po terminie</span><div class="muted">termin {{ $complaint->responseDeadline()->format('d.m.Y') }}</div>
@else
    <span class="badge badge-warn">czeka</span><div class="muted">do {{ $complaint->responseDeadline()->format('d.m.Y') }}</div>
@endif
