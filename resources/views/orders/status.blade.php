@if ($order->status)
    {{ $order->status->name }}
    <div>@if ($order->status->group === \App\Enums\StatusGroup::DoUstalenia)<span class="badge badge-warn">{{ $order->status->group->label() }}</span>@else<span class="badge">{{ $order->status->group->label() }}</span>@endif</div>
@else
    <span class="badge badge-warn">nieznany status{{ $order->baselinker_status_id ? ' ('.$order->baselinker_status_id.')' : '' }}</span>
@endif
