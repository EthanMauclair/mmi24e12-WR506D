<?php

namespace App\Enum;

enum EtatArticle: string
{
    case NEUF_AVEC_ETIQUETTE = 'NEUF_AVEC_ETIQUETTE';
    case NEUF_SANS_ETIQUETTE = 'NEUF_SANS_ETIQUETTE';
    case TRES_BON_ETAT = 'TRES_BON_ETAT';
    case BON_ETAT = 'BON_ETAT';
    case SATISFAISANT = 'SATISFAISANT';

    public function label(): string
    {
        return match ($this) {
            self::NEUF_AVEC_ETIQUETTE => 'Neuf avec étiquette',
            self::NEUF_SANS_ETIQUETTE => 'Neuf sans étiquette',
            self::TRES_BON_ETAT       => 'Très bon état',
            self::BON_ETAT            => 'Bon état',
            self::SATISFAISANT        => 'Satisfaisant',
        };
    }
}
