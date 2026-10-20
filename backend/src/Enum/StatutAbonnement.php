<?php

namespace App\Enum;

enum StatutAbonnement: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case ACCEPTE = 'ACCEPTE';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::ACCEPTE    => 'Accepté',
        };
    }
}
