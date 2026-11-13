<?php

namespace App\Enums;

enum RefundMethod: string
{
    case TaSamaMetoda = 'ta_sama_metoda';
    case Przelew = 'przelew';
    case Marketplace = 'marketplace';
    case Gotowka = 'gotowka';

    public function label(): string
    {
        return match ($this) {
            self::TaSamaMetoda => 'Ta sama metoda co płatność (PayU, Przelewy24, Stripe, PayPal)',
            self::Przelew => 'Przelew na konto klienta',
            self::Marketplace => 'Przez marketplace (Allegro, Amazon, Kaufland)',
            self::Gotowka => 'Gotówka',
        };
    }
}
