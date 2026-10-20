<?php

namespace App\Entity;

use App\Enum\EtatArticle;
use App\Enum\ModeAjout;
use App\Enum\StatutArticle;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Logement $logement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $proprietaire = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Stockage $stockage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Categorie $categorie = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true, enumType: EtatArticle::class)]
    private ?EtatArticle $etat = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $estPersonnel = false;

    #[ORM\Column(enumType: StatutArticle::class, options: ['default' => 'DISPONIBLE'])]
    private StatutArticle $statut = StatutArticle::DISPONIBLE;

    #[ORM\Column(enumType: ModeAjout::class)]
    #[Assert\NotNull]
    private ?ModeAjout $modeAjout = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Etiquette>
     */
    #[ORM\ManyToMany(targetEntity: Etiquette::class)]
    #[ORM\JoinTable(name: 'article_etiquette')]
    #[ORM\JoinColumn(name: 'article_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'etiquette_id', onDelete: 'CASCADE')]
    private Collection $etiquettes;

    /**
     * @var Collection<int, PhotoArticle>
     */
    #[ORM\OneToMany(targetEntity: PhotoArticle::class, mappedBy: 'article', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $photos;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->etiquettes = new ArrayCollection();
        $this->photos = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function majUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validerStockage(ExecutionContextInterface $context): void
    {
        if (null !== $this->stockage && $this->stockage->getPiece()?->getLogement() !== $this->logement) {
            $context->buildViolation('Le stockage doit appartenir au logement de l\'article.')
                ->atPath('stockage')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogement(): ?Logement
    {
        return $this->logement;
    }

    public function setLogement(?Logement $logement): static
    {
        $this->logement = $logement;

        return $this;
    }

    public function getProprietaire(): ?Utilisateur
    {
        return $this->proprietaire;
    }

    public function setProprietaire(?Utilisateur $proprietaire): static
    {
        $this->proprietaire = $proprietaire;

        return $this;
    }

    public function getStockage(): ?Stockage
    {
        return $this->stockage;
    }

    public function setStockage(?Stockage $stockage): static
    {
        $this->stockage = $stockage;

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

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

    public function getEtat(): ?EtatArticle
    {
        return $this->etat;
    }

    public function setEtat(?EtatArticle $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function isEstPersonnel(): bool
    {
        return $this->estPersonnel;
    }

    public function setEstPersonnel(bool $estPersonnel): static
    {
        $this->estPersonnel = $estPersonnel;

        return $this;
    }

    public function getStatut(): StatutArticle
    {
        return $this->statut;
    }

    public function setStatut(StatutArticle $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getModeAjout(): ?ModeAjout
    {
        return $this->modeAjout;
    }

    public function setModeAjout(ModeAjout $modeAjout): static
    {
        $this->modeAjout = $modeAjout;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Etiquette>
     */
    public function getEtiquettes(): Collection
    {
        return $this->etiquettes;
    }

    public function addEtiquette(Etiquette $etiquette): static
    {
        if (!$this->etiquettes->contains($etiquette)) {
            $this->etiquettes->add($etiquette);
        }

        return $this;
    }

    public function removeEtiquette(Etiquette $etiquette): static
    {
        $this->etiquettes->removeElement($etiquette);

        return $this;
    }

    /**
     * @return Collection<int, PhotoArticle>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(PhotoArticle $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setArticle($this);
        }

        return $this;
    }

    public function removePhoto(PhotoArticle $photo): static
    {
        $this->photos->removeElement($photo);

        return $this;
    }
}
