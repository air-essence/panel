<?php

namespace App\Enums;

/** „Co dzieje się z produktem” z arkuszy reklamacji. */
enum ProductLocation: string
{
    case UKlienta = 'u_klienta';
    case WDrodze = 'w_drodze';
    case UNas = 'u_nas';
    case WSerwisie = 'w_serwisie';
    case OdeslanyKlientowi = 'odeslany_klientowi';
    case Zutylizowany = 'zutylizowany';

    public function label(): string
    {
        return match ($this) {
            self::UKlienta => 'U klienta',
            self::WDrodze => 'W drodze do nas',
            self::UNas => 'U nas',
            self::WSerwisie => 'W serwisie zewnętrznym',
            self::OdeslanyKlientowi => 'Odesłany klientowi',
            self::Zutylizowany => 'Zutylizowany',
        };
    }
}
