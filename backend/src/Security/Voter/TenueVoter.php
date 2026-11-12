<?php

namespace App\Security\Voter;

use App\Entity\Tenue;
use App\Entity\Utilisateur;
use App\Service\Social\Relations;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * VOIR : une tenue privée par son créateur uniquement ; une tenue publique par tous ceux qui voient son profil.
 * AIMER : tout membre connecté qui voit la tenue.
 * COMMENTER : comme pour les articles, son créateur et ses amis.
 * SUPPRIMER : son créateur.
 *
 * @extends Voter<string, Tenue>
 */
final class TenueVoter extends Voter
{
    public const VOIR = 'TENUE_VOIR';
    public const AIMER = 'TENUE_AIMER';
    public const COMMENTER = 'TENUE_COMMENTER';
    public const SUPPRIMER = 'TENUE_SUPPRIMER';

    public function __construct(private readonly Relations $relations)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::AIMER, self::COMMENTER, self::SUPPRIMER], true) && $subject instanceof Tenue;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        $utilisateur = $utilisateur instanceof Utilisateur ? $utilisateur : null;
        $createur = $subject->getCreateur();

        $voit = $subject->isEstPrivee()
            ? $createur === $utilisateur
            : $this->relations->peutVoirContenu($utilisateur, $createur);

        return match ($attribute) {
            self::VOIR => $voit,
            self::AIMER => $voit && null !== $utilisateur,
            self::COMMENTER => $voit && $this->relations->peutCommenter($utilisateur, $createur),
            self::SUPPRIMER => null !== $utilisateur && $createur === $utilisateur,
            default => false,
        };
    }
}
