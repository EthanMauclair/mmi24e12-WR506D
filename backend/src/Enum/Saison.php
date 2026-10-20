<?php

namespace App\Enum;

enum Saison: string
{
    case TOUTES = 'TOUTES';
    case PRINTEMPS_ETE = 'PRINTEMPS_ETE';
    case AUTOMNE_HIVER = 'AUTOMNE_HIVER';

    public function label(): string
    {
        return match ($this) {
            self::TOUTES        => 'Toutes saisons',
            self::PRINTEMPS_ETE => 'Printemps-été',
            self::AUTOMNE_HIVER => 'Automne-hiver',
        };
    }
}
