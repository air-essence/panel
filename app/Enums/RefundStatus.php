<?php

namespace App\Enums;

enum RefundStatus: string
{
    case NieDotyczy = 'nie_dotyczy';
    case DoZwrotu = 'do_zwrotu';
    case Zwrocono = 'zwrocono';

    public function label(): string
    {
        return match ($this) {
            self::NieDotyczy => 'Nie dotyczy (nic nie wpłacono)',
            self::DoZwrotu => 'Do zwrotu',
            self::Zwrocono => 'Zwrócono',
        };
    }
}
