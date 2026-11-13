<?php

namespace App\Enums;

/**
 * Grupy statusów zamówień używane przez reguły rozbieżności.
 * Każdy status z BaseLinkera / WooCommerce mapujemy na dokładnie jedną grupę.
 */
enum StatusGroup: string
{
    case CzekaNaPlatnosc = 'czeka_na_platnosc';
    case WRealizacji = 'w_realizacji';
    case Wyslane = 'wyslane';
    case Zakonczone = 'zakonczone';
    case Reklamacja = 'reklamacja';
    case AnulowaneZwrocone = 'anulowane_zwrocone';
    case PrzeniesioneDoSpolki = 'przeniesione_do_spolki';
    case DoUstalenia = 'do_ustalenia';

    public function label(): string
    {
        return match ($this) {
            self::CzekaNaPlatnosc => 'Czeka na płatność',
            self::WRealizacji => 'W realizacji',
            self::Wyslane => 'Wysłane',
            self::Zakonczone => 'Zakończone',
            self::Reklamacja => 'Reklamacja',
            self::AnulowaneZwrocone => 'Anulowane / zwrócone',
            self::PrzeniesioneDoSpolki => 'Przeniesione do innej spółki',
            self::DoUstalenia => 'Do ustalenia',
        };
    }
}
