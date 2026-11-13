<?php

namespace App\Enums;

enum Severity: string
{
    case Krytyczna = 'krytyczna';
    case Ostrzezenie = 'ostrzezenie';

    public function label(): string
    {
        return match ($this) {
            self::Krytyczna => 'Krytyczna',
            self::Ostrzezenie => 'Ostrzeżenie',
        };
    }
}
