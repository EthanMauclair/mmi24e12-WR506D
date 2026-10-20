<?php

namespace App\Enum;

enum ZoneTenue: string
{
    case HAUT = 'HAUT';
    case BAS = 'BAS';
    case UNE_PIECE = 'UNE_PIECE';
    case COUCHE_EXTERIEURE = 'COUCHE_EXTERIEURE';
    case CHAUSSURES = 'CHAUSSURES';
    case ACCESSOIRE = 'ACCESSOIRE';

    public function label(): string
    {
        return match ($this) {
            self::HAUT              => 'Haut',
            self::BAS               => 'Bas',
            self::UNE_PIECE         => 'Une pièce',
            self::COUCHE_EXTERIEURE => 'Couche extérieure',
            self::CHAUSSURES        => 'Chaussures',
            self::ACCESSOIRE        => 'Accessoire',
        };
    }
}
