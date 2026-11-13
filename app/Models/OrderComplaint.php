<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\ComplaintResolution;
use App\Enums\ProductLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Reklamacja produktu z zamówienia, łącznie z naprawą w serwisie zewnętrznym.
 */
class OrderComplaint extends Model
{
    /** Ustawowy termin odpowiedzi na reklamację konsumenta (dni od zgłoszenia). */
    public const RESPONSE_DEADLINE_DAYS = 14;

    /** Po tylu dniach w serwisie zewnętrznym panel przypomina o produkcie. */
    public const SERVICE_REMINDER_DAYS = 30;

    public const TRACKED = [
        'status' => 'Status sprawy',
        'product_name' => 'Produkt',
        'ean' => 'EAN',
        'reported_on' => 'Data zgłoszenia',
        'defect_description' => 'Opis wady',
        'product_location' => 'Gdzie jest produkt',
        'service_name' => 'Serwis',
        'service_sent_on' => 'Wysłano do serwisu',
        'service_returned_on' => 'Wróciło z serwisu',
        'responded_on' => 'Odpowiedź dla klienta',
        'resolution' => 'Rozstrzygnięcie',
        'resolved_on' => 'Data rozstrzygnięcia',
        'wz_number' => 'Numer WZ',
        'drive_link' => 'Link do zdjęć/dokumentów',
        'notes' => 'Uwagi',
        'assigned_to' => 'Osoba prowadząca',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'nowa'];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'product_location' => ProductLocation::class,
            'resolution' => ComplaintResolution::class,
            'reported_on' => 'date',
            'service_sent_on' => 'date',
            'service_returned_on' => 'date',
            'responded_on' => 'date',
            'resolved_on' => 'date',
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
        return $this->hasMany(OrderComplaintEvent::class)->latest('created_at')->latest('id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', CaseStatus::Zakonczona->value);
    }

    public function responseDeadline(): Carbon
    {
        return $this->reported_on->copy()->addDays(self::RESPONSE_DEADLINE_DAYS);
    }

    public function isResponseOverdue(): bool
    {
        return $this->responded_on === null && $this->status->isOpen() && $this->responseDeadline()->isBefore(today());
    }

    public function isLongInService(): bool
    {
        return $this->product_location === ProductLocation::WSerwisie
            && $this->service_sent_on !== null
            && $this->service_sent_on->copy()->addDays(self::SERVICE_REMINDER_DAYS)->isBefore(today());
    }
}
