<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BaselinkerSource extends Model
{
    protected $guarded = ['id'];

    public function salesChannel(): HasOne
    {
        return $this->hasOne(SalesChannel::class, 'baselinker_source', 'key');
    }

    public function label(): string
    {
        return "{$this->name} ({$this->type})";
    }
}
