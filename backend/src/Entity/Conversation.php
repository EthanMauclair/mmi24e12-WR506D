<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_conversation', columns: ['annonce_id', 'acheteur_id'])]
#[UniqueEntity(fields: ['annonce', 'acheteur'], message: 'Une conversation existe déjà pour cette annonce.')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Annonce $annonce = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $acheteur = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['envoyeAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validerAcheteur(ExecutionContextInterface $context): void
    {
        if (null !== $this->acheteur && $this->annonce?->getArticle()?->getProprietaire() === $this->acheteur) {
            $context->buildViolation('Le vendeur ne peut pas être l\'acheteur de son propre article.')
                ->atPath('acheteur')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnonce(): ?Annonce
    {
        return $this->annonce;
    }

    public function setAnnonce(?Annonce $annonce): static
    {
        $this->annonce = $annonce;

        return $this;
    }

    public function getAcheteur(): ?Utilisateur
    {
        return $this->acheteur;
    }

    public function setAcheteur(?Utilisateur $acheteur): static
    {
        $this->acheteur = $acheteur;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        $this->messages->removeElement($message);

        return $this;
    }
}
