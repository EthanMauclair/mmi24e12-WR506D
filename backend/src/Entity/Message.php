<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $expediteur = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $contenu = null;

    #[ORM\Column]
    private \DateTimeImmutable $envoyeAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $luAt = null;

    public function __construct()
    {
        $this->envoyeAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getExpediteur(): ?Utilisateur
    {
        return $this->expediteur;
    }

    public function setExpediteur(?Utilisateur $expediteur): static
    {
        $this->expediteur = $expediteur;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getEnvoyeAt(): \DateTimeImmutable
    {
        return $this->envoyeAt;
    }

    public function getLuAt(): ?\DateTimeImmutable
    {
        return $this->luAt;
    }

    public function setLuAt(?\DateTimeImmutable $luAt): static
    {
        $this->luAt = $luAt;

        return $this;
    }
}
