<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Enums\CaseStatus;
use App\Enums\RefundStatus;
use App\Enums\ShipmentHold;
use App\Enums\StatusGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprawa do zamówienia. W MVP wyłącznie anulacje (type = anulacja).
 */
class OrderCase extends Model
{
    public const TYPE_CANCELLATION = 'anulacja';

    /** Pola, których zmiany zapisujemy w historii, z etykietami do wyświetlenia. */
    public const TRACKED = [
        'status' => 'Status sprawy',
        'reason' => 'Powód',
        'reason_details' => 'Opis powodu',
        'requested_on' => 'Data zgłoszenia',
        'refund_status' => 'Zwrot pieniędzy',
        'refund_amount' => 'Kwota zwrotu',
        'refunded_on' => 'Data zwrotu',
        'shipment_hold' => 'Przesyłka',
        'notes' => 'Uwagi',
        'assigned_to' => 'Osoba prowadząca',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['type' => self::TYPE_CANCELLATION, 'status' => 'nowa'];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'reason' => CancellationReason::class,
            'refund_status' => RefundStatus::class,
            'shipment_hold' => ShipmentHold::class,
            'refund_amount' => 'decimal:2',
            'requested_on' => 'date',
            'refunded_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderCaseEvent::class)->latest('created_at')->latest('id');
    }

    public function scopeCancellations(Builder $query): void
    {
        $query->where('type', self::TYPE_CANCELLATION);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', CaseStatus::Zakonczona->value);
    }

    /**
     * Ostrzeżenia z porównania sprawy z danymi zamówienia z BaseLinkera.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $order = $this->order;
        $warnings = [];

        if (! $order) {
            return $warnings;
        }

        if ($order->tracking_number && $this->shipment_hold === ShipmentHold::NieNadano) {
            $warnings[] = "Zamówienie ma numer przesyłki {$order->tracking_number}, a w sprawie wpisano, że paczka nie została nadana.";
        }

        if ($this->shipment_hold === ShipmentHold::Nadano && $this->status?->isOpen()) {
            $warnings[] = 'Paczka została wysłana. Dopilnuj zwrotu towaru, zanim zamkniesz sprawę.';
        }

        if ((float) $order->paid > 0 && $this->refund_status === RefundStatus::NieDotyczy) {
            $warnings[] = 'Klient zapłacił '.number_format((float) $order->paid, 2, ',', ' ')." {$order->currency}, a w sprawie wpisano, że zwrot nie dotyczy.";
        }

        if ($this->refund_status === RefundStatus::Zwrocono && $this->refund_amount !== null
            && (int) round((float) $this->refund_amount * 100) !== (int) round((float) $order->paid * 100)) {
            $warnings[] = 'Kwota zwrotu różni się od kwoty wpłaconej ('.number_format((float) $order->paid, 2, ',', ' ')." {$order->currency}).";
        }

        if ($order->status && $order->status->group !== StatusGroup::AnulowaneZwrocone) {
            $warnings[] = "W BaseLinkerze zamówienie ma status „{$order->status->name}”, a nie anulowane. Zmień status w BaseLinkerze.";
        }

        return $warnings;
    }
}
