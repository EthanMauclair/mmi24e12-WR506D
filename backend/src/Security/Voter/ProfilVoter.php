<?php

namespace App\Security\Voter;

use App\Entity\Utilisateur;
use App\Service\Social\Relations;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * VOIR_CONTENU : articles, abonnés et abonnements d'un profil (voir Relations).
 * Visiteurs sans compte compris, pour un profil public.
 *
 * @extends Voter<string, Utilisateur>
 */
final class ProfilVoter extends Voter
{
    public const VOIR_CONTENU = 'PROFIL_VOIR_CONTENU';

    public function __construct(private readonly Relations $relations)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VOIR_CONTENU === $attribute && $subject instanceof Utilisateur;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $visiteur = $token->getUser();

        return $this->relations->peutVoirContenu($visiteur instanceof Utilisateur ? $visiteur : null, $subject);
    }
}
