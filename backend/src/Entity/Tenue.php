<?php

namespace App\Entity;

use App\Repository\TenueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TenueRepository::class)]
class Tenue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $createur = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $titre = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $estPrivee = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\ManyToMany(targetEntity: Article::class)]
    #[ORM\JoinTable(name: 'tenue_article')]
    #[ORM\JoinColumn(name: 'tenue_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'article_id', onDelete: 'CASCADE')]
    #[Assert\Count(min: 1, minMessage: 'Une tenue contient au moins un article.')]
    private Collection $articles;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->articles = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validerArticles(ExecutionContextInterface $context): void
    {
        foreach ($this->articles as $article) {
            if ($article->getProprietaire() !== $this->createur) {
                $context->buildViolation('Une tenue ne contient que des articles de son créateur.')
                    ->atPath('articles')
                    ->addViolation();

                return;
            }

            if (!$this->estPrivee && $article->isEstPersonnel()) {
                $context->buildViolation('Un article personnel ne peut pas figurer dans une tenue publique.')
                    ->atPath('articles')
                    ->addViolation();

                return;
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreateur(): ?Utilisateur
    {
        return $this->createur;
    }

    public function setCreateur(?Utilisateur $createur): static
    {
        $this->createur = $createur;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

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

    public function isEstPrivee(): bool
    {
        return $this->estPrivee;
    }

    public function setEstPrivee(bool $estPrivee): static
    {
        $this->estPrivee = $estPrivee;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        $this->articles->removeElement($article);

        return $this;
    }
}
