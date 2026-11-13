<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Nowa = 'nowa';
    case WTrakcie = 'w_trakcie';
    case Zakonczona = 'zakonczona';

    public function label(): string
    {
        return match ($this) {
            self::Nowa => 'Nowa',
            self::WTrakcie => 'W trakcie',
            self::Zakonczona => 'Zakończona',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Zakonczona;
    }
}
