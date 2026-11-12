<?php

namespace App\Security\Voter;

use App\Entity\Article;
use App\Entity\Utilisateur;
use App\Enum\StatutArticle;
use App\Repository\AnnonceRepository;
use App\Service\Social\Relations;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * VOIR : membre du logement (seul celui qui l'a ajouté voit un article personnel), ou, par le réseau social,
 *        toute personne qui voit le profil de celui qui l'a ajouté (jamais un article personnel ou vendu),
 *        ou, sur le marché, tout membre connecté tant que l'article a une annonce en ligne ou réservée.
 * VOIR_EMPLACEMENT : membre du logement uniquement (un visiteur ou un abonné ne voit jamais où l'article est rangé).
 * MODIFIER : celui qui l'a ajouté, ou le propriétaire du logement.
 * CHANGER_CONFIDENTIALITE (article personnel ou non) : celui qui l'a ajouté uniquement.
 * COMMENTER : celui qui l'a ajouté et ses amis, sur un article qui n'est pas personnel.
 * VENDRE : celui qui l'a ajouté (le vendeur est le propriétaire de l'article).
 *
 * @extends Voter<string, Article>
 */
final class ArticleVoter extends Voter
{
    public const VOIR = 'ARTICLE_VOIR';
    public const VOIR_EMPLACEMENT = 'ARTICLE_VOIR_EMPLACEMENT';
    public const MODIFIER = 'ARTICLE_MODIFIER';
    public const CHANGER_CONFIDENTIALITE = 'ARTICLE_CHANGER_CONFIDENTIALITE';
    public const COMMENTER = 'ARTICLE_COMMENTER';
    public const VENDRE = 'ARTICLE_VENDRE';

    public function __construct(
        private readonly Relations $relations,
        private readonly AnnonceRepository $annonces,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::VOIR_EMPLACEMENT, self::MODIFIER, self::CHANGER_CONFIDENTIALITE, self::COMMENTER, self::VENDRE], true)
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();
        $utilisateur = $utilisateur instanceof Utilisateur ? $utilisateur : null;

        $estAuteur = null !== $utilisateur && $subject->getProprietaire() === $utilisateur;
        $voitDansLogement = null !== $utilisateur
            && null !== $subject->getLogement()?->roleDe($utilisateur)
            && (!$subject->isEstPersonnel() || $estAuteur);
        $voitParProfil = !$subject->isEstPersonnel()
            && StatutArticle::VENDU !== $subject->getStatut()
            && $this->relations->peutVoirContenu($utilisateur, $subject->getProprietaire());

        return match ($attribute) {
            self::VOIR => $voitDansLogement || $voitParProfil
                || (null !== $utilisateur && StatutArticle::EN_VENTE === $subject->getStatut() && null !== $this->annonces->findActive($subject)),
            self::VENDRE => $voitDansLogement && $estAuteur,
            self::VOIR_EMPLACEMENT => $voitDansLogement,
            self::MODIFIER => $voitDansLogement && ($estAuteur || $subject->getLogement()->getProprietaire() === $utilisateur),
            self::CHANGER_CONFIDENTIALITE => $estAuteur,
            self::COMMENTER => !$subject->isEstPersonnel() && $this->relations->peutCommenter($utilisateur, $subject->getProprietaire()),
            default => false,
        };
    }
}
