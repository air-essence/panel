<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public static function latestFor(string $source): ?self
    {
        return static::where('source', $source)->latest('started_at')->latest('id')->first();
    }

    public function succeeded(): bool
    {
        return $this->finished_at !== null && $this->error === null;
    }
}
