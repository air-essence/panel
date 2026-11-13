<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_cod' => 'boolean',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'ordered_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'synced_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(OrderCase::class)->latest();
    }

    public function isPaid(): bool
    {
        $total = (int) round((float) $this->total * 100);

        return $total > 0 && (int) round((float) $this->paid * 100) >= $total;
    }
}
