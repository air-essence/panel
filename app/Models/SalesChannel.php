<?php

namespace App\Models;

use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'type', 'company_id', 'baselinker_source', 'is_active'])]
class SalesChannel extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Po przypisaniu źródła BaseLinkera zamówienia z tego źródła trafiają do kanału.
        static::saved(function (SalesChannel $channel) {
            if ($channel->wasChanged('baselinker_source') || $channel->wasRecentlyCreated) {
                Order::where('sales_channel_id', $channel->id)
                    ->where(fn ($q) => $q->whereNull('baselinker_source')->orWhere('baselinker_source', '!=', $channel->baselinker_source ?? ''))
                    ->update(['sales_channel_id' => null]);

                if ($channel->baselinker_source) {
                    Order::where('baselinker_source', $channel->baselinker_source)->update(['sales_channel_id' => $channel->id]);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
