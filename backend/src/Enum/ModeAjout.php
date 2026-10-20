<?php

namespace App\Enum;

enum ModeAjout: string
{
    case SCAN_IMAGE = 'SCAN_IMAGE';
    case SCAN_CODE_BARRE = 'SCAN_CODE_BARRE';
    case MANUEL = 'MANUEL';

    public function label(): string
    {
        return match ($this) {
            self::SCAN_IMAGE      => 'Scan d\'image',
            self::SCAN_CODE_BARRE => 'Scan de code-barres',
            self::MANUEL          => 'Saisie manuelle',
        };
    }
}
