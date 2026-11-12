<?php

namespace App\Repository;

use App\Entity\Annonce;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Conversations du membre, comme acheteur ou comme vendeur, la plus récemment active d'abord.
     *
     * @return list<Conversation>
     */
    public function findDuMembre(Utilisateur $membre, ?Annonce $annonce = null): array
    {
        $requete = $this->createQueryBuilder('c')
            ->join('c.annonce', 'an')->addSelect('an')
            ->join('an.article', 'a')->addSelect('a')
            ->join('c.acheteur', 'ac')->addSelect('ac')
            ->join('a.proprietaire', 'v')->addSelect('v')
            ->leftJoin('c.messages', 'm')
            ->andWhere('c.acheteur = :membre OR a.proprietaire = :membre')
            ->setParameter('membre', $membre)
            ->addSelect('MAX(COALESCE(m.envoyeAt, c.createdAt)) AS HIDDEN derniereActivite')
            ->groupBy('c.id')
            ->orderBy('derniereActivite', 'DESC');

        if (null !== $annonce) {
            $requete->andWhere('c.annonce = :annonce')->setParameter('annonce', $annonce);
        }

        return $requete->getQuery()->getResult();
    }

    /**
     * Dernier message et nombre de messages non lus par le membre, pour chaque conversation.
     *
     * @param list<Conversation> $conversations
     *
     * @return array<int, array{dernier: ?Message, nonLus: int}> par identifiant de conversation
     */
    public function resumer(array $conversations, Utilisateur $membre): array
    {
        if ([] === $conversations) {
            return [];
        }

        $messages = $this->getEntityManager()->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->andWhere('m.conversation IN (:conversations)')
            ->setParameter('conversations', $conversations)
            ->orderBy('m.envoyeAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        $resume = [];
        foreach ($messages as $message) {
            $id = $message->getConversation()->getId();
            $nonLu = $message->getExpediteur() !== $membre && null === $message->getLuAt();
            $resume[$id] = ['dernier' => $message, 'nonLus' => ($resume[$id]['nonLus'] ?? 0) + ($nonLu ? 1 : 0)];
        }

        return $resume;
    }

    public function compterNonLus(Utilisateur $membre): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->join('c.annonce', 'an')
            ->join('an.article', 'a')
            ->andWhere('c.acheteur = :membre OR a.proprietaire = :membre')
            ->andWhere('m.expediteur != :membre')
            ->andWhere('m.luAt IS NULL')
            ->setParameter('membre', $membre)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
