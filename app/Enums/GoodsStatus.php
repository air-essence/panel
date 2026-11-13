<?php

namespace App\Enums;

enum GoodsStatus: string
{
    case WDrodze = 'w_drodze';
    case Sprawny = 'sprawny';
    case Uszkodzony = 'uszkodzony';
    case NieDotyczy = 'nie_dotyczy';

    public function label(): string
    {
        return match ($this) {
            self::WDrodze => 'Czekamy na paczkę',
            self::Sprawny => 'Dotarł, sprawny (do odsprzedaży)',
            self::Uszkodzony => 'Dotarł, uszkodzony lub niekompletny',
            self::NieDotyczy => 'Nie dotyczy (rabat)',
        };
    }

    public function arrived(): bool
    {
        return in_array($this, [self::Sprawny, self::Uszkodzony], true);
    }
}
