<?php

namespace App\Enum;

enum FamilleCategorie: string
{
    case VETEMENT = 'VETEMENT';
    case ACCESSOIRE = 'ACCESSOIRE';
    case LIVRE = 'LIVRE';
    case VINYLE = 'VINYLE';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::VETEMENT   => 'Vêtement',
            self::ACCESSOIRE => 'Accessoire',
            self::LIVRE      => 'Livre',
            self::VINYLE     => 'Vinyle',
            self::AUTRE      => 'Autre',
        };
    }
}
