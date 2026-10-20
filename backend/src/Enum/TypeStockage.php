<?php

namespace App\Enum;

enum TypeStockage: string
{
    case ARMOIRE = 'ARMOIRE';
    case PENDERIE = 'PENDERIE';
    case COMMODE = 'COMMODE';
    case ETAGERE = 'ETAGERE';
    case PLACARD = 'PLACARD';
    case BIBLIOTHEQUE = 'BIBLIOTHEQUE';
    case BOITE = 'BOITE';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::ARMOIRE      => 'Armoire',
            self::PENDERIE     => 'Penderie',
            self::COMMODE      => 'Commode',
            self::ETAGERE      => 'Étagère',
            self::PLACARD      => 'Placard',
            self::BIBLIOTHEQUE => 'Bibliothèque',
            self::BOITE        => 'Boîte',
            self::AUTRE        => 'Autre',
        };
    }
}
