<?php

namespace App\Service\Social;

use App\Entity\Abonnement;
use App\Entity\Utilisateur;
use App\Enum\StatutAbonnement;
use App\Repository\AbonnementRepository;

/**
 * Règles du réseau social, en un seul endroit :
 * - le contenu d'un profil public est visible par tous, visiteurs sans compte compris ;
 * - celui d'un profil privé, par ses abonnés acceptés uniquement ;
 * - seuls les amis (abonnés choisis comme proches par le membre) commentent ses articles ;
 * - un membre voit et commente toujours son propre contenu.
 */
final class Relations
{
    public function __construct(private readonly AbonnementRepository $abonnements)
    {
    }

    public function abonnement(?Utilisateur $abonne, Utilisateur $suivi): ?Abonnement
    {
        return null === $abonne || $abonne === $suivi ? null : $this->abonnements->entre($abonne, $suivi);
    }

    public function peutVoirContenu(?Utilisateur $visiteur, Utilisateur $membre): bool
    {
        return $visiteur === $membre
            || !$membre->isEstPrive()
            || StatutAbonnement::ACCEPTE === $this->abonnement($visiteur, $membre)?->getStatut();
    }

    public function estAmi(?Utilisateur $visiteur, Utilisateur $membre): bool
    {
        return true === $this->abonnement($visiteur, $membre)?->isEstAmi();
    }

    public function peutCommenter(?Utilisateur $visiteur, Utilisateur $membre): bool
    {
        return null !== $visiteur && ($visiteur === $membre || $this->estAmi($visiteur, $membre));
    }
}
