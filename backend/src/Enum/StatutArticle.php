<?php

namespace App\Enum;

enum StatutArticle: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case PRETE = 'PRETE';
    case EN_VENTE = 'EN_VENTE';
    case VENDU = 'VENDU';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'Disponible',
            self::PRETE      => 'Prêté',
            self::EN_VENTE   => 'En vente',
            self::VENDU      => 'Vendu',
        };
    }
}
