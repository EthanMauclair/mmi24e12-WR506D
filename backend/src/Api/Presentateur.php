<?php

namespace App\Api;

use App\Entity\Annonce;
use App\Entity\Article;
use App\Entity\Commentaire;
use App\Entity\Conversation;
use App\Entity\DetailLivre;
use App\Entity\DetailVetement;
use App\Entity\DetailVinyle;
use App\Entity\Logement;
use App\Entity\Message;
use App\Entity\Pret;
use App\Entity\Tenue;
use App\Entity\Utilisateur;
use App\Enum\RoleLogement;
use App\Service\Traduction\Referentiels;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Met en forme les entités pour les réponses JSON de l'API.
 */
final class Presentateur
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Referentiels $referentiels,
    ) {
    }

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
            // Accès au back office : référentiels et modération (admin), membres et indicateurs (super admin)
            'estAdmin' => $utilisateur->estAdmin(),
            'estSuperAdmin' => $utilisateur->estSuperAdmin(),
        ];
    }

    /**
     * Membre tel que les autres le voient (listes, auteur d'un commentaire…) : jamais son e-mail.
     *
     * @return array<string, mixed>
     */
    public function membre(Utilisateur $membre): array
    {
        $nomComplet = trim(($membre->getPrenom() ?? '').' '.($membre->getNom() ?? ''));

        return [
            'id' => $membre->getId(),
            'pseudo' => $membre->getPseudo(),
            'nomComplet' => '' === $nomComplet ? null : $nomComplet,
            'avatar' => $membre->getAvatar(),
            'estPrive' => $membre->isEstPrive(),
        ];
    }

    /**
     * Articles d'un profil : comme articlesResumes, sans ce qui dit où ils sont rangés.
     *
     * @param list<Article> $articles
     *
     * @return list<array<string, mixed>>
     */
    public function articlesDuProfil(array $articles): array
    {
        return array_map(
            static fn (array $article) => array_diff_key($article, ['stockageId' => true, 'emplacement' => true]),
            $this->articlesResumes($articles),
        );
    }

    /**
     * Tenue enregistrée : ses articles sans leur emplacement, comme sur un profil.
     *
     * @return array<string, mixed>
     */
    public function tenue(Tenue $tenue): array
    {
        return [
            'id' => $tenue->getId(),
            'titre' => $tenue->getTitre(),
            'description' => $tenue->getDescription(),
            'estPrivee' => $tenue->isEstPrivee(),
            'createdAt' => $tenue->getCreatedAt()->format(\DATE_ATOM),
            'createur' => $this->membre($tenue->getCreateur()),
            'articles' => $this->articlesDuProfil($tenue->getArticles()->getValues()),
        ];
    }

    /**
     * Annonces du marché avec le résumé de leur article, sans son emplacement.
     *
     * @param list<Annonce> $annonces
     *
     * @return list<array<string, mixed>>
     */
    public function annonces(array $annonces): array
    {
        $resumes = [];
        foreach ($this->articlesDuProfil(array_map(static fn (Annonce $annonce) => $annonce->getArticle(), $annonces)) as $resume) {
            $resumes[$resume['id']] = $resume;
        }

        return array_map(fn (Annonce $annonce) => [
            'id' => $annonce->getId(),
            'prix' => (float) $annonce->getPrix(),
            'description' => $annonce->getDescription(),
            'statut' => $annonce->getStatut()->value,
            'createdAt' => $annonce->getCreatedAt()->format(\DATE_ATOM),
            'vendeur' => $this->membre($annonce->getArticle()->getProprietaire()),
            'acheteur' => null === $annonce->getAcheteur() ? null : $this->membre($annonce->getAcheteur()),
            'article' => $resumes[$annonce->getArticle()->getId()],
        ], $annonces);
    }

    /**
     * Conversations vues par le membre : son interlocuteur, son rôle, le dernier message et les non lus.
     *
     * @param list<Conversation>                                    $conversations
     * @param array<int, array{dernier: ?Message, nonLus: int}>     $resumes       voir ConversationRepository::resumer
     *
     * @return list<array<string, mixed>>
     */
    public function conversations(array $conversations, array $resumes, Utilisateur $membre): array
    {
        $uniques = [];
        foreach ($conversations as $conversation) {
            $uniques[$conversation->getAnnonce()->getId()] = $conversation->getAnnonce();
        }
        $annonces = [];
        foreach ($this->annonces(array_values($uniques)) as $annonce) {
            $annonces[$annonce['id']] = $annonce;
        }

        return array_map(function (Conversation $conversation) use ($annonces, $resumes, $membre) {
            $estVendeur = $conversation->getAnnonce()->getArticle()->getProprietaire() === $membre;
            $dernier = $resumes[$conversation->getId()]['dernier'] ?? null;

            return [
                'id' => $conversation->getId(),
                'annonce' => $annonces[$conversation->getAnnonce()->getId()],
                'acheteur' => $this->membre($conversation->getAcheteur()),
                'estVendeur' => $estVendeur,
                'interlocuteur' => $this->membre($estVendeur ? $conversation->getAcheteur() : $conversation->getAnnonce()->getArticle()->getProprietaire()),
                'dernierMessage' => null === $dernier ? null : $this->message($dernier, $membre),
                'nonLus' => $resumes[$conversation->getId()]['nonLus'] ?? 0,
            ];
        }, $conversations);
    }

    /**
     * @return array<string, mixed>
     */
    public function message(Message $message, Utilisateur $membre): array
    {
        return [
            'id' => $message->getId(),
            'contenu' => $message->getContenu(),
            'envoyeAt' => $message->getEnvoyeAt()->format(\DATE_ATOM),
            'deMoi' => $message->getExpediteur() === $membre,
            'lu' => null !== $message->getLuAt(),
        ];
    }

    /**
     * Prêts avec un résumé de leur article (photo, couleur, titre), en une requête par type de détail.
     *
     * @param list<Pret> $prets
     *
     * @return list<array<string, mixed>>
     */
    public function prets(array $prets): array
    {
        $resumes = [];
        foreach ($this->articlesResumes(array_map(static fn (Pret $pret) => $pret->getArticle(), $prets)) as $resume) {
            $resumes[$resume['id']] = array_intersect_key($resume, array_flip(['id', 'titre', 'infos', 'photo', 'couleur']));
        }

        return array_map(fn (Pret $pret) => $this->pret($pret) + ['article' => $resumes[$pret->getArticle()->getId()]], $prets);
    }

    /**
     * @return array<string, mixed>
     */
    public function pret(Pret $pret): array
    {
        $prevue = $pret->getDateRetourPrevue();

        return [
            'id' => $pret->getId(),
            'emprunteur' => null === $pret->getEmprunteur() ? null : $this->membre($pret->getEmprunteur()),
            'emprunteurNom' => $pret->getEmprunteurNom(),
            'datePret' => $pret->getDatePret()->format('Y-m-d'),
            'dateRetourPrevue' => $prevue?->format('Y-m-d'),
            'dateRetourReelle' => $pret->getDateRetourReelle()?->format('Y-m-d'),
            'note' => $pret->getNote(),
            'enCours' => $pret->estEnCours(),
            // Négatif quand le retour prévu est dépassé
            'joursRestants' => $pret->estEnCours() && null !== $prevue
                ? (int) (new \DateTimeImmutable('today'))->diff($prevue)->format('%r%a')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function commentaire(Commentaire $commentaire, bool $peutSupprimer): array
    {
        return [
            'id' => $commentaire->getId(),
            'auteur' => $this->membre($commentaire->getAuteur()),
            'contenu' => $commentaire->getContenu(),
            'createdAt' => $commentaire->getCreatedAt()->format(\DATE_ATOM),
            'peutSupprimer' => $peutSupprimer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function logementResume(Logement $logement, Utilisateur $utilisateur): array
    {
        $role = $logement->roleDe($utilisateur);

        return [
            'id' => $logement->getId(),
            'nom' => $logement->getNom(),
            'role' => $role instanceof RoleLogement ? $role->value : $role,
            'nbCoproprietaires' => \count(array_filter(
                $logement->getMembres()->toArray(),
                static fn ($membre) => RoleLogement::COPROPRIETAIRE === $membre->getRole(),
            )),
        ];
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<array<string, mixed>>
     */
    public function articlesResumes(array $articles): array
    {
        $details = $this->details($articles);

        return array_map(function (Article $article) use ($details) {
            $vetement = $details[DetailVetement::class][$article->getId()] ?? null;
            $premierePhoto = $article->getPhotos()->first();

            return [
                'id' => $article->getId(),
                'titre' => $article->getTitre(),
                'infos' => $this->infos($article, $details, false),
                'categorie' => $this->referentiels->nom($article->getCategorie()->getNom()),
                'statut' => $article->getStatut()->value,
                'estPersonnel' => $article->isEstPersonnel(),
                'couleur' => $vetement?->getCouleurPrincipale()?->getCodeHex(),
                'photo' => $premierePhoto ? $premierePhoto->getChemin() : null,
                'stockageId' => $article->getStockage()?->getId(),
                'emplacement' => $this->emplacement($article),
            ];
        }, $articles);
    }

    /**
     * Fiche d'un article. Sans le droit voitEmplacement (visiteur, abonné), ni l'emplacement, ni le logement,
     * ni le prêt en cours ne sont renvoyés.
     *
     * @param array{peutModifier: bool, peutChangerConfidentialite: bool, voitEmplacement: bool, peutCommenter: bool} $droits
     *
     * @return array<string, mixed>
     */
    public function articleDetail(Article $article, array $droits, ?Pret $pretEnCours = null): array
    {
        $peutModifier = $droits['peutModifier'];
        $voitEmplacement = $droits['voitEmplacement'];
        $details = $this->details([$article]);
        $vetement = $details[DetailVetement::class][$article->getId()] ?? null;
        $couleur = $vetement?->getCouleurPrincipale();
        $stockage = $article->getStockage();
        $photos = $article->getPhotos()->getValues();
        $photoScan = array_values(array_filter($photos, static fn ($photo) => $photo->isEstImageScan()))[0] ?? null;

        return [
            'id' => $article->getId(),
            'titre' => $article->getTitre(),
            'infos' => $this->infos($article, $details, true),
            'description' => $article->getDescription(),
            'statut' => $article->getStatut()->value,
            'categorie' => $this->referentiels->nom($article->getCategorie()->getNom()),
            'couleur' => null === $couleur ? null : ['nom' => $this->referentiels->nom($couleur->getNom()), 'codeHex' => $couleur->getCodeHex()],
            'photos' => array_map(static fn ($photo) => $photo->getChemin(), $photos),
            'dateScan' => $photoScan?->getCreatedAt()->format(\DATE_ATOM),
            'voitEmplacement' => $voitEmplacement,
            'emplacement' => null === $stockage || !$voitEmplacement ? null : [
                'piece' => ['id' => $stockage->getPiece()->getId(), 'nom' => $stockage->getPiece()->getNom()],
                'stockage' => ['id' => $stockage->getId(), 'nom' => $stockage->getNom()],
            ],
            'logementId' => $voitEmplacement ? $article->getLogement()->getId() : null,
            'proprietaire' => $this->membre($article->getProprietaire()),
            'etiquettes' => array_map(
                static fn ($etiquette) => ['id' => $etiquette->getId(), 'nom' => $etiquette->getNom()],
                $article->getEtiquettes()->getValues(),
            ),
            'estPersonnel' => $article->isEstPersonnel(),
            'peutModifier' => $peutModifier,
            'peutChangerConfidentialite' => $droits['peutChangerConfidentialite'],
            'peutCommenter' => $droits['peutCommenter'],
            'pretEnCours' => null === $pretEnCours || !$voitEmplacement ? null : $this->pret($pretEnCours),
            'formulaire' => $peutModifier ? $this->formulaire($article, $details) : null,
        ];
    }

    /**
     * Valeurs brutes de l'article, pour pré-remplir le formulaire de modification.
     *
     * @param array<class-string, array<int, object>> $details
     *
     * @return array<string, mixed>
     */
    private function formulaire(Article $article, array $details): array
    {
        $id = $article->getId();
        $vetement = $details[DetailVetement::class][$id] ?? null;
        $livre = $details[DetailLivre::class][$id] ?? null;
        $vinyle = $details[DetailVinyle::class][$id] ?? null;

        return [
            'titre' => $article->getTitre(),
            'description' => $article->getDescription(),
            'etat' => $article->getEtat()?->value,
            'categorieId' => $article->getCategorie()->getId(),
            'stockageId' => $article->getStockage()?->getId(),
            'etiquetteIds' => array_map(static fn ($etiquette) => $etiquette->getId(), $article->getEtiquettes()->getValues()),
            'vetement' => null === $vetement ? null : [
                'marque' => $vetement->getMarque()?->getNom(),
                'couleurId' => $vetement->getCouleurPrincipale()?->getId(),
                'taille' => $vetement->getTaille(),
                'matiere' => $vetement->getMatiere(),
            ],
            'livre' => null === $livre ? null : [
                'isbn' => $livre->getIsbn(),
                'auteur' => $livre->getAuteur(),
                'editeur' => $livre->getEditeur(),
                'nbPages' => $livre->getNbPages(),
                'anneeParution' => $livre->getAnneeParution(),
                'serie' => $livre->getSerie(),
                'tome' => $livre->getTome(),
            ],
            'vinyle' => null === $vinyle ? null : [
                'ean' => $vinyle->getEan(),
                'artiste' => $vinyle->getArtiste(),
                'label' => $vinyle->getLabel(),
                'anneeSortie' => $vinyle->getAnneeSortie(),
                'format' => $vinyle->getFormat()?->value,
                'nbDisques' => $vinyle->getNbDisques(),
            ],
        ];
    }

    private function emplacement(Article $article): ?string
    {
        $stockage = $article->getStockage();

        return null === $stockage ? null : $stockage->getPiece()->getNom().' › '.$stockage->getNom();
    }

    /**
     * Ligne d'informations sous le titre, selon la famille de l'article.
     * Version courte (cartes) : « COS · Bordeaux · M ». Version longue (fiche) : « COS · Laine mérinos · Taille M ».
     *
     * @param array<class-string, array<int, object>> $details
     */
    private function infos(Article $article, array $details, bool $complet): string
    {
        $id = $article->getId();
        $morceaux = [];

        if ($vetement = $details[DetailVetement::class][$id] ?? null) {
            $morceaux = $complet
                ? [$vetement->getMarque()?->getNom(), $vetement->getMatiere(), $vetement->getTaille() ? $this->referentiels->libelle('Taille %taille%', ['%taille%' => $vetement->getTaille()]) : null]
                : [$vetement->getMarque()?->getNom(), $this->referentiels->nom($vetement->getCouleurPrincipale()?->getNom()), $vetement->getTaille()];
        } elseif ($livre = $details[DetailLivre::class][$id] ?? null) {
            $morceaux = [$livre->getAuteur(), $livre->getEditeur(), $complet ? $livre->getAnneeParution() : null];
        } elseif ($vinyle = $details[DetailVinyle::class][$id] ?? null) {
            $morceaux = [$vinyle->getArtiste(), $vinyle->getLabel(), $complet ? $vinyle->getAnneeSortie() : null];
        }

        $morceaux = array_filter($morceaux, static fn ($morceau) => null !== $morceau && '' !== $morceau);

        return [] === $morceaux ? $this->referentiels->nom($article->getCategorie()->getNom()) : implode(' · ', $morceaux);
    }

    /**
     * Charge en une requête par type les détails (vêtement, livre, vinyle) des articles.
     *
     * @param list<Article> $articles
     *
     * @return array<class-string, array<int, object>> détails par classe puis par identifiant d'article
     */
    private function details(array $articles): array
    {
        $details = [];
        foreach ([DetailVetement::class, DetailLivre::class, DetailVinyle::class] as $classe) {
            $details[$classe] = [];
            if ([] === $articles) {
                continue;
            }
            foreach ($this->em->getRepository($classe)->findBy(['article' => $articles]) as $detail) {
                $details[$classe][$detail->getArticle()->getId()] = $detail;
            }
        }

        return $details;
    }
}
