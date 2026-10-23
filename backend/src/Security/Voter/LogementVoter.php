<?php

namespace App\Security\Voter;

use App\Entity\Logement;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * VOIR : propriétaire ou membre du logement. GERER (pièces, stockages) : propriétaire uniquement.
 *
 * @extends Voter<string, Logement>
 */
final class LogementVoter extends Voter
{
    public const VOIR = 'LOGEMENT_VOIR';
    public const GERER = 'LOGEMENT_GERER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::GERER], true) && $subject instanceof Logement;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        return match ($attribute) {
            self::VOIR => null !== $subject->roleDe($utilisateur),
            self::GERER => $subject->getProprietaire() === $utilisateur,
            default => false,
        };
    }
}
