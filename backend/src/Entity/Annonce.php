<?php

namespace App\Entity;

use App\Enum\StatutAnnonce;
use App\Repository\AnnonceRepository;
use App\Validator\UneSeuleAnnonceActive;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AnnonceRepository::class)]
#[UneSeuleAnnonceActive]
class Annonce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Article $article = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Utilisateur $acheteur = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $prix = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: StatutAnnonce::class, options: ['default' => 'EN_LIGNE'])]
    private StatutAnnonce $statut = StatutAnnonce::EN_LIGNE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $vendueAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function estActive(): bool
    {
        return \in_array($this->statut, [StatutAnnonce::EN_LIGNE, StatutAnnonce::RESERVEE], true);
    }

    #[Assert\Callback]
    public function validerArticle(ExecutionContextInterface $context): void
    {
        if (null !== $this->article && $this->article->isEstPersonnel()) {
            $context->buildViolation('Un article personnel ne peut pas être mis en vente.')
                ->atPath('article')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

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

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatut(): StatutAnnonce
    {
        return $this->statut;
    }

    public function setStatut(StatutAnnonce $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getVendueAt(): ?\DateTimeImmutable
    {
        return $this->vendueAt;
    }

    public function setVendueAt(?\DateTimeImmutable $vendueAt): static
    {
        $this->vendueAt = $vendueAt;

        return $this;
    }
}
