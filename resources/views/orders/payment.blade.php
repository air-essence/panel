@if ($order->is_cod)
    <span class="badge">pobranie</span>
@elseif ($order->isPaid())
    <span class="badge badge-ok">opłacone</span>
@elseif ((float) $order->paid > 0)
    <span class="badge badge-warn">częściowo</span>
@else
    <span class="badge">nieopłacone</span>
@endif
<div class="muted">{{ $order->payment_method }}</div>
