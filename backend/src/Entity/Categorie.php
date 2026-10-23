<?php

namespace App\Entity;

use App\Enum\FamilleCategorie;
use App\Enum\ZoneTenue;
use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_categorie_parent_nom', columns: ['parent_id', 'nom'])]
#[UniqueEntity(fields: ['parent', 'nom'], ignoreNull: false, message: 'Cette catégorie existe déjà à cet endroit.')]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'enfants')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Categorie $parent = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $nom = null;

    #[ORM\Column(enumType: FamilleCategorie::class)]
    #[Assert\NotNull]
    private ?FamilleCategorie $famille = null;

    #[ORM\Column(nullable: true, enumType: ZoneTenue::class)]
    private ?ZoneTenue $zoneTenue = null;

    /**
     * @var Collection<int, Categorie>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $enfants;

    public function __construct()
    {
        $this->enfants = new ArrayCollection();
    }

    /**
     * Chemin complet depuis la racine, ex. « Femmes › Pulls et sweats › Pulls ».
     */
    public function getChemin(): string
    {
        $noms = [];
        for ($categorie = $this; null !== $categorie; $categorie = $categorie->getParent()) {
            array_unshift($noms, $categorie->getNom());
        }

        return implode(' › ', $noms);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?Categorie
    {
        return $this->parent;
    }

    public function setParent(?Categorie $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getFamille(): ?FamilleCategorie
    {
        return $this->famille;
    }

    public function setFamille(FamilleCategorie $famille): static
    {
        $this->famille = $famille;

        return $this;
    }

    public function getZoneTenue(): ?ZoneTenue
    {
        return $this->zoneTenue;
    }

    public function setZoneTenue(?ZoneTenue $zoneTenue): static
    {
        $this->zoneTenue = $zoneTenue;

        return $this;
    }

    /**
     * @return Collection<int, Categorie>
     */
    public function getEnfants(): Collection
    {
        return $this->enfants;
    }

    public function addEnfant(Categorie $enfant): static
    {
        if (!$this->enfants->contains($enfant)) {
            $this->enfants->add($enfant);
            $enfant->setParent($this);
        }

        return $this;
    }
}
