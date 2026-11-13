<?php

namespace App\Enums;

/** Co stało się z przesyłką anulowanego zamówienia. */
enum ShipmentHold: string
{
    case NieNadano = 'nie_nadano';
    case Wstrzymano = 'wstrzymano';
    case Nadano = 'nadano';

    public function label(): string
    {
        return match ($this) {
            self::NieNadano => 'Paczka nie została nadana',
            self::Wstrzymano => 'Nadanie wstrzymane / paczka zatrzymana',
            self::Nadano => 'Paczka już wysłana (czekamy na zwrot)',
        };
    }
}
