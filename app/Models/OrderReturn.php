<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\GoodsStatus;
use App\Enums\RefundMethod;
use App\Enums\ReturnKind;
use App\Enums\SaleDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Zwrot do zamówienia: odstąpienie od umowy, zwrot części albo rabat.
 */
class OrderReturn extends Model
{
    /** Ustawowy termin zwrotu pieniędzy przy odstąpieniu od umowy (dni od zgłoszenia). */
    public const REFUND_DEADLINE_DAYS = 14;

    public const TRACKED = [
        'status' => 'Status sprawy',
        'kind' => 'Rodzaj',
        'requested_on' => 'Data zgłoszenia',
        'goods_status' => 'Towar',
        'received_on' => 'Data otrzymania towaru',
        'goods_location' => 'Lokalizacja towaru',
        'return_tracking_number' => 'Numer przesyłki zwrotnej',
        'amount' => 'Kwota zwrotu',
        'refund_method' => 'Metoda zwrotu',
        'refund_account' => 'Konto do zwrotu',
        'refunded_on' => 'Data zwrotu pieniędzy',
        'sale_document' => 'Dokument sprzedaży',
        'sale_document_number' => 'Numer dokumentu sprzedaży',
        'correction_document_number' => 'Numer korekty / PZ',
        'notes' => 'Uwagi',
        'assigned_to' => 'Osoba prowadząca',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'nowa'];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'kind' => ReturnKind::class,
            'goods_status' => GoodsStatus::class,
            'refund_method' => RefundMethod::class,
            'sale_document' => SaleDocument::class,
            'amount' => 'decimal:2',
            'requested_on' => 'date',
            'received_on' => 'date',
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
        return $this->hasMany(OrderReturnEvent::class)->latest('created_at')->latest('id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', CaseStatus::Zakonczona->value);
    }

    public function refundDeadline(): ?Carbon
    {
        return $this->requested_on?->copy()->addDays(self::REFUND_DEADLINE_DAYS);
    }

    public function isRefundOverdue(): bool
    {
        return $this->refunded_on === null
            && $this->kind === ReturnKind::Odstapienie
            && $this->refundDeadline()?->isBefore(today());
    }
}
