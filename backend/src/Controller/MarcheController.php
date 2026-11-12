<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Entity\Annonce;
use App\Entity\Article;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Utilisateur;
use App\Enum\FamilleCategorie;
use App\Enum\StatutAnnonce;
use App\Enum\StatutArticle;
use App\Repository\AnnonceRepository;
use App\Repository\ConversationRepository;
use App\Repository\UtilisateurRepository;
use App\Security\Voter\ArticleVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Place de marché interne : annonces de vente et messagerie entre acheteur et vendeur.
 * Aucun paiement : acheteur et vendeur s'arrangent entre eux.
 *
 * Le vendeur est le membre qui a ajouté l'article. Les annonces en ligne ou réservées sont visibles par tous
 * les membres connectés, sans l'emplacement de l'article ; une conversation, par ses deux participants seulement.
 */
#[Route('/api')]
final class MarcheController extends ApiController
{
    /** Filtres du marché vers les familles de catégories */
    private const FAMILLES = [
        'vetements' => [FamilleCategorie::VETEMENT, FamilleCategorie::ACCESSOIRE],
        'vinyles' => [FamilleCategorie::VINYLE],
        'livres' => [FamilleCategorie::LIVRE],
    ];

    public function __construct(
        private readonly Presentateur $presentateur,
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Annonces du marché. Paramètres facultatifs : q (texte), famille (vetements, vinyles, livres), mes (1 : ses annonces).
     */
    #[Route('/annonces', name: 'api_annonces', methods: ['GET'])]
    public function lister(Request $request, #[CurrentUser] Utilisateur $utilisateur, AnnonceRepository $annonces): JsonResponse
    {
        $requete = $request->query;

        return $this->json([
            'annonces' => $this->presentateur->annonces($annonces->rechercher(
                trim((string) $requete->get('q', '')),
                self::FAMILLES[$requete->get('famille')] ?? [],
                $requete->getBoolean('mes') ? $utilisateur : null,
            )),
            'nonLus' => $this->conversations->compterNonLus($utilisateur),
        ]);
    }

    #[Route('/annonces/{id}', name: 'api_annonce', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(#[MapEntity(id: 'id')] Annonce $annonce, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $this->verifierVisible($annonce, $utilisateur);

        return $this->json($this->presenterAnnonce($annonce, $utilisateur));
    }

    /**
     * Met un article en vente. Corps : prix (en euros), description facultative.
     */
    #[Route('/articles/{id}/annonce', name: 'api_article_vendre', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ArticleVoter::VENDRE, 'article', message: 'Seul le membre qui a ajouté cet article peut le vendre.')]
    public function vendre(#[MapEntity(id: 'id')] Article $article, Request $request, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        if (StatutArticle::DISPONIBLE !== $article->getStatut()) {
            return $this->reponseInvalide(['general' => 'Seul un article disponible (ni prêté, ni déjà en vente) peut être mis en vente.']);
        }

        $donnees = $this->lireJson($request);
        $annonce = (new Annonce())->setArticle($article);
        if (null !== $messages = $this->appliquer($annonce, $donnees)) {
            return $this->reponseInvalide($messages);
        }

        $article->setStatut(StatutArticle::EN_VENTE);
        $this->em->persist($annonce);
        $this->em->flush();

        return $this->json($this->presenterAnnonce($annonce, $utilisateur), Response::HTTP_CREATED);
    }

    /**
     * Le vendeur modifie le prix ou la description, réserve l'annonce, la remet en ligne, la marque vendue
     * (acheteur : pseudo facultatif) ou la retire.
     */
    #[Route('/annonces/{id}', name: 'api_annonce_modifier', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function modifier(
        #[MapEntity(id: 'id')] Annonce $annonce,
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        UtilisateurRepository $utilisateurs,
    ): JsonResponse {
        $this->verifierVendeur($annonce, $utilisateur);
        if (!$annonce->estActive()) {
            return $this->reponseInvalide(['general' => 'Cette annonce est terminée : elle ne peut plus être modifiée.']);
        }

        $donnees = $this->lireJson($request);
        if (null !== $messages = $this->appliquer($annonce, $donnees + ['prix' => $annonce->getPrix(), 'description' => $annonce->getDescription()])) {
            return $this->reponseInvalide($messages);
        }

        $article = $annonce->getArticle();
        $statut = \is_string($donnees['statut'] ?? null) ? StatutAnnonce::tryFrom($donnees['statut']) : null;
        if (\array_key_exists('statut', $donnees) && null === $statut) {
            return $this->reponseInvalide(['statut' => 'Statut inconnu.']);
        }

        if (StatutAnnonce::VENDUE === $statut) {
            $acheteur = null;
            if (\is_string($donnees['acheteur'] ?? null) && '' !== trim($donnees['acheteur'])) {
                $acheteur = $utilisateurs->findOneBy(['pseudo' => ltrim(trim($donnees['acheteur']), '@')]);
                if (null === $acheteur || $acheteur === $utilisateur) {
                    return $this->reponseInvalide(['acheteur' => 'Choisissez l\'acheteur parmi les membres qui vous ont écrit.']);
                }
            }
            // Vendu : l'article quitte le dressing (il n'a plus d'emplacement)
            $annonce->setAcheteur($acheteur)->setVendueAt(new \DateTimeImmutable());
            $article->setStatut(StatutArticle::VENDU)->setStockage(null);
        } elseif (StatutAnnonce::RETIREE === $statut) {
            $article->setStatut(StatutArticle::DISPONIBLE);
        }
        if (null !== $statut) {
            $annonce->setStatut($statut);
        }

        $this->em->flush();

        return $this->json($this->presenterAnnonce($annonce, $utilisateur));
    }

    /**
     * L'acheteur écrit au vendeur : la conversation est créée au premier message, puis réutilisée.
     */
    #[Route('/annonces/{id}/conversations', name: 'api_annonce_contacter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function contacter(#[MapEntity(id: 'id')] Annonce $annonce, Request $request, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        if (!$annonce->estActive()) {
            return $this->reponseInvalide(['general' => 'Cette annonce n\'est plus en ligne.']);
        }

        $conversation = $this->conversations->findOneBy(['annonce' => $annonce, 'acheteur' => $utilisateur])
            ?? (new Conversation())->setAnnonce($annonce)->setAcheteur($utilisateur);
        $messages = $this->messagesParChamp($this->validator->validate($conversation), ['acheteur' => 'general']);
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $message = $this->nouveauMessage($conversation, $utilisateur, $this->lireJson($request)['contenu'] ?? null);
        if ($message instanceof JsonResponse) {
            return $message;
        }
        $this->em->persist($conversation);
        $this->em->flush();

        return $this->json($this->presenterConversation($conversation, $utilisateur), Response::HTTP_CREATED);
    }

    /**
     * Ses conversations (comme acheteur ou vendeur) et le nombre total de messages non lus.
     */
    #[Route('/conversations', name: 'api_conversations', methods: ['GET'])]
    public function conversations(#[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $conversations = $this->conversations->findDuMembre($utilisateur);

        return $this->json([
            'conversations' => $this->presentateur->conversations($conversations, $this->conversations->resumer($conversations, $utilisateur), $utilisateur),
            'nonLus' => $this->conversations->compterNonLus($utilisateur),
        ]);
    }

    /**
     * Une conversation et ses messages ; les messages reçus sont marqués comme lus.
     */
    #[Route('/conversations/{id}', name: 'api_conversation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function conversation(#[MapEntity(id: 'id')] Conversation $conversation, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $this->verifierParticipant($conversation, $utilisateur);

        $maintenant = new \DateTimeImmutable();
        foreach ($conversation->getMessages() as $message) {
            if ($message->getExpediteur() !== $utilisateur && null === $message->getLuAt()) {
                $message->setLuAt($maintenant);
            }
        }
        $this->em->flush();

        return $this->json($this->presenterConversation($conversation, $utilisateur));
    }

    #[Route('/conversations/{id}/messages', name: 'api_conversation_ecrire', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function ecrire(#[MapEntity(id: 'id')] Conversation $conversation, Request $request, #[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        $this->verifierParticipant($conversation, $utilisateur);

        $message = $this->nouveauMessage($conversation, $utilisateur, $this->lireJson($request)['contenu'] ?? null);
        if ($message instanceof JsonResponse) {
            return $message;
        }
        $this->em->flush();

        return $this->json($this->presentateur->message($message, $utilisateur), Response::HTTP_CREATED);
    }

    /**
     * Prix et description depuis le corps de la requête.
     *
     * @param array<string, mixed> $donnees
     *
     * @return array<string, string>|null messages d'erreur par champ, ou null si tout est valide
     */
    private function appliquer(Annonce $annonce, array $donnees): ?array
    {
        $prix = str_replace([',', ' ', '€'], ['.', '', ''], trim((string) ($donnees['prix'] ?? '')));
        if (!is_numeric($prix) || (float) $prix > 99999.99) {
            return ['prix' => 'Indiquez un prix en euros, par exemple 18 ou 12,50.'];
        }
        $description = \is_string($donnees['description'] ?? null) ? trim($donnees['description']) : '';
        $annonce->setPrix(number_format((float) $prix, 2, '.', ''))->setDescription('' === $description ? null : mb_substr($description, 0, 2000));

        $messages = $this->messagesParChamp($this->validator->validate($annonce), ['article' => 'general']);

        return [] === $messages ? null : $messages;
    }

    private function nouveauMessage(Conversation $conversation, Utilisateur $expediteur, mixed $contenu): Message|JsonResponse
    {
        $message = (new Message())->setExpediteur($expediteur)->setContenu(\is_string($contenu) ? trim(mb_substr($contenu, 0, 2000)) : '');
        $conversation->addMessage($message);

        $messages = $this->messagesParChamp($this->validator->validate($message));
        if ([] !== $messages) {
            $conversation->removeMessage($message);

            return $this->reponseInvalide(['contenu' => $messages['contenu'] ?? 'Écrivez un message.']);
        }

        return $message;
    }

    /**
     * Une annonce terminée (vendue, retirée) n'est plus visible que par son vendeur et son acheteur.
     */
    private function verifierVisible(Annonce $annonce, Utilisateur $utilisateur): void
    {
        $vendeur = $annonce->getArticle()->getProprietaire();
        if (!$annonce->estActive() && $vendeur !== $utilisateur && $annonce->getAcheteur() !== $utilisateur) {
            throw $this->createNotFoundException('Cette annonce n\'est plus en ligne.');
        }
    }

    private function verifierVendeur(Annonce $annonce, Utilisateur $utilisateur): void
    {
        if ($annonce->getArticle()->getProprietaire() !== $utilisateur) {
            throw $this->createAccessDeniedException('Seul le vendeur peut modifier cette annonce.');
        }
    }

    private function verifierParticipant(Conversation $conversation, Utilisateur $utilisateur): void
    {
        if ($conversation->getAcheteur() !== $utilisateur && $conversation->getAnnonce()->getArticle()->getProprietaire() !== $utilisateur) {
            throw $this->createNotFoundException('Conversation introuvable.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presenterAnnonce(Annonce $annonce, Utilisateur $utilisateur): array
    {
        $estVendeur = $annonce->getArticle()->getProprietaire() === $utilisateur;
        // Le vendeur voit toutes les conversations de l'annonce ; un acheteur, seulement la sienne
        $conversations = $estVendeur
            ? $this->conversations->findDuMembre($utilisateur, $annonce)
            : array_filter([$this->conversations->findOneBy(['annonce' => $annonce, 'acheteur' => $utilisateur])]);
        $conversations = array_values($conversations);

        return $this->presentateur->annonces([$annonce])[0] + [
            'estVendeur' => $estVendeur,
            'conversations' => $this->presentateur->conversations($conversations, $this->conversations->resumer($conversations, $utilisateur), $utilisateur),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presenterConversation(Conversation $conversation, Utilisateur $utilisateur): array
    {
        return $this->presentateur->conversations([$conversation], $this->conversations->resumer([$conversation], $utilisateur), $utilisateur)[0] + [
            'messages' => array_map(fn (Message $message) => $this->presentateur->message($message, $utilisateur), $conversation->getMessages()->getValues()),
        ];
    }
}
