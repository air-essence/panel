<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'short_name'])]
class Company extends Model
{
    use HasFactory;

    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class);
    }
}
