<?php

namespace App\Security\Voter;

use App\Entity\Article;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * VOIR : membre du logement, et seul celui qui l'a ajouté voit un article personnel.
 * MODIFIER : celui qui l'a ajouté, ou le propriétaire du logement.
 * CHANGER_CONFIDENTIALITE (article personnel ou non) : celui qui l'a ajouté uniquement.
 *
 * @extends Voter<string, Article>
 */
final class ArticleVoter extends Voter
{
    public const VOIR = 'ARTICLE_VOIR';
    public const MODIFIER = 'ARTICLE_MODIFIER';
    public const CHANGER_CONFIDENTIALITE = 'ARTICLE_CHANGER_CONFIDENTIALITE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::MODIFIER, self::CHANGER_CONFIDENTIALITE], true)
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }

        $estAuteur = $subject->getProprietaire() === $utilisateur;
        $peutVoir = null !== $subject->getLogement()?->roleDe($utilisateur)
            && (!$subject->isEstPersonnel() || $estAuteur);

        return match ($attribute) {
            self::VOIR => $peutVoir,
            self::MODIFIER => $peutVoir && ($estAuteur || $subject->getLogement()->getProprietaire() === $utilisateur),
            self::CHANGER_CONFIDENTIALITE => $estAuteur,
            default => false,
        };
    }
}
