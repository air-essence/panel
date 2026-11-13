<?php

namespace App\Enums;

enum ReturnKind: string
{
    case Odstapienie = 'odstapienie';
    case Czesciowy = 'czesciowy';
    case Rabat = 'rabat';

    public function label(): string
    {
        return match ($this) {
            self::Odstapienie => 'Odstąpienie od umowy (zwrot całości)',
            self::Czesciowy => 'Zwrot części zamówienia',
            self::Rabat => 'Rabat bez odsyłania towaru',
        };
    }

    public function needsGoods(): bool
    {
        return $this !== self::Rabat;
    }
}
