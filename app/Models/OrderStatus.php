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

    protected function casts(): array
    {
        return [
            'group' => StatusGroup::class,
            'baselinker_status_id' => 'integer',
        ];
    }
}
