<?php

namespace App\Enums;

enum ComplaintResolution: string
{
    case NowyProdukt = 'nowy_produkt';
    case ZwrotPieniedzy = 'zwrot_pieniedzy';
    case Naprawa = 'naprawa';
    case Gratis = 'gratis';
    case NaprawaOdplatna = 'naprawa_odplatna';
    case Odrzucona = 'odrzucona';

    public function label(): string
    {
        return match ($this) {
            self::NowyProdukt => 'Nowy produkt',
            self::ZwrotPieniedzy => 'Zwrot pieniędzy',
            self::Naprawa => 'Naprawa',
            self::Gratis => 'Gratis / rekompensata',
            self::NaprawaOdplatna => 'Naprawa na koszt klienta',
            self::Odrzucona => 'Reklamacja odrzucona',
        };
    }
}
