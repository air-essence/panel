@switch($case->refund_status)
    @case(\App\Enums\RefundStatus::Zwrocono)
        <span class="badge badge-ok">zwrócono</span> {{ number_format((float) $case->refund_amount, 2, ',', ' ') }}
        <div class="muted">{{ $case->refunded_on?->format('d.m.Y') }}</div>
        @break
    @case(\App\Enums\RefundStatus::DoZwrotu)
        <span class="badge badge-warn">do zwrotu</span> {{ number_format((float) $case->refund_amount, 2, ',', ' ') }}
        @break
    @default
        <span class="muted">nie dotyczy</span>
@endswitch
