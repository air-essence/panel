<?php

namespace App\Enums;

enum SaleDocument: string
{
    case Paragon = 'paragon';
    case Faktura = 'faktura';

    public function label(): string
    {
        return match ($this) {
            self::Paragon => 'Paragon',
            self::Faktura => 'Faktura',
        };
    }
}
