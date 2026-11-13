<?php

namespace App\Enums;

enum ChannelType: string
{
    case WooCommerce = 'woocommerce';
    case Allegro = 'allegro';
    case Kaufland = 'kaufland';
    case Amazon = 'amazon';
    case Inny = 'inny';

    public function label(): string
    {
        return match ($this) {
            self::WooCommerce => 'Sklep WooCommerce',
            self::Allegro => 'Allegro',
            self::Kaufland => 'Kaufland',
            self::Amazon => 'Amazon',
            self::Inny => 'Inny',
        };
    }
}
