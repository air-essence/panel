<?php

namespace App\Enums;

enum Role: string
{
    case Pracownik = 'pracownik';
    case Kierownik = 'kierownik';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Pracownik => 'Pracownik',
            self::Kierownik => 'Kierownik',
            self::Admin => 'Administrator',
        };
    }
}
