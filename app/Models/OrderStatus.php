<?php

namespace App\Models;

use App\Enums\StatusGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'group', 'baselinker_status_id', 'notes'])]
class OrderStatus extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Po przypisaniu ID statusu z BaseLinkera zamówienia w tym statusie łączą się z nim.
        static::saved(function (OrderStatus $status) {
            if ($status->wasChanged('baselinker_status_id') || $status->wasRecentlyCreated) {
                Order::where('order_status_id', $status->id)
                    ->where(fn ($q) => $q->whereNull('baselinker_status_id')->orWhere('baselinker_status_id', '!=', $status->baselinker_status_id ?? 0))
                    ->update(['order_status_id' => null]);

                if ($status->baselinker_status_id) {
                    Order::where('baselinker_status_id', $status->baselinker_status_id)->update(['order_status_id' => $status->id]);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'group' => StatusGroup::class,
            'baselinker_status_id' => 'integer',
        ];
    }
}
