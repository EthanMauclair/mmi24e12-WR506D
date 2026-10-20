<?php

namespace App\Enum;

enum FormatVinyle: string
{
    case LP_33T = 'LP_33T';
    case SINGLE_45T = 'SINGLE_45T';
    case MAXI_45T = 'MAXI_45T';
    case EP = 'EP';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::LP_33T     => 'LP 33 tours',
            self::SINGLE_45T => 'Single 45 tours',
            self::MAXI_45T   => 'Maxi 45 tours',
            self::EP         => 'EP',
            self::AUTRE      => 'Autre',
        };
    }
}
