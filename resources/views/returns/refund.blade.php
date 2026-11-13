@if ($return->refunded_on)
    <span class="badge badge-ok">zwrócono</span><div class="muted">{{ $return->refunded_on->format('d.m.Y') }}</div>
@elseif ($return->isRefundOverdue())
    <span class="badge badge-danger">po terminie</span><div class="muted">termin {{ $return->refundDeadline()->format('d.m.Y') }}</div>
@else
    <span class="badge badge-warn">do zwrotu</span>@if ($return->kind === \App\Enums\ReturnKind::Odstapienie)<div class="muted">do {{ $return->refundDeadline()->format('d.m.Y') }}</div>@endif
@endif
