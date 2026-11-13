<?php

namespace App\Enums;

enum CancellationReason: string
{
    case RezygnacjaKlienta = 'rezygnacja_klienta';
    case BrakTowaru = 'brak_towaru';
    case BrakPlatnosci = 'brak_platnosci';
    case BladWZamowieniu = 'blad_w_zamowieniu';
    case Duplikat = 'duplikat';
    case Inny = 'inny';

    public function label(): string
    {
        return match ($this) {
            self::RezygnacjaKlienta => 'Rezygnacja klienta',
            self::BrakTowaru => 'Brak towaru',
            self::BrakPlatnosci => 'Brak płatności',
            self::BladWZamowieniu => 'Błąd w zamówieniu',
            self::Duplikat => 'Zdublowane zamówienie',
            self::Inny => 'Inny (opisz)',
        };
    }
}
