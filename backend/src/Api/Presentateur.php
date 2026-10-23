<?php

namespace App\Api;

use App\Entity\Utilisateur;

/**
 * Met en forme les entités pour les réponses JSON de l'API.
 */
final class Presentateur
{
    /**
     * @return array<string, mixed>
     */
    public function utilisateur(Utilisateur $utilisateur): array
    {
        return [
            'id' => $utilisateur->getId(),
            'pseudo' => $utilisateur->getPseudo(),
            'email' => $utilisateur->getEmail(),
            'estPrive' => $utilisateur->isEstPrive(),
            'estMineur' => $utilisateur->estMineur(),
        ];
    }
}
