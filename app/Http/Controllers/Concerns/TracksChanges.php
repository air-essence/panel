<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zbiera zmiany pól sprawy w czytelnej postaci do historii („stara wartość → nowa”).
 */
trait TracksChanges
{
    /** Pola kwot formatowane jako „1 234,50”. */
    protected array $moneyFields = ['refund_amount', 'amount'];

    /**
     * @param  array<string, string>  $tracked  pole => etykieta
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    protected function changes(Model $model, array $tracked): array
    {
        $changes = [];

        foreach (array_keys($tracked) as $field) {
            if (! $model->isDirty($field)) {
                continue;
            }

            $old = $this->display($field, $model->getOriginal($field));
            $new = $this->display($field, $model->getAttribute($field));

            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    protected function display(string $field, mixed $value): ?string
    {
        return match (true) {
            $value === null || $value === '' => null,
            $value instanceof BackedEnum && method_exists($value, 'label') => $value->label(),
            $value instanceof Carbon => $value->format('d.m.Y'),
            $field === 'assigned_to' => User::find($value)?->name,
            in_array($field, $this->moneyFields, true) => number_format((float) $value, 2, ',', ' '),
            default => (string) $value,
        };
    }

    protected function activeUsers()
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
