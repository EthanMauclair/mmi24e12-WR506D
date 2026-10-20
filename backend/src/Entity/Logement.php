<?php

namespace App\Entity;

use App\Repository\LogementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LogementRepository::class)]
class Logement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'logements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $proprietaire = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, MembreLogement>
     */
    #[ORM\OneToMany(targetEntity: MembreLogement::class, mappedBy: 'logement', cascade: ['persist'], orphanRemoval: true)]
    private Collection $membres;

    /**
     * @var Collection<int, Piece>
     */
    #[ORM\OneToMany(targetEntity: Piece::class, mappedBy: 'logement', cascade: ['persist'], orphanRemoval: true)]
    private Collection $pieces;

    /**
     * @var Collection<int, Etiquette>
     */
    #[ORM\OneToMany(targetEntity: Etiquette::class, mappedBy: 'logement', cascade: ['persist'], orphanRemoval: true)]
    private Collection $etiquettes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->membres = new ArrayCollection();
        $this->pieces = new ArrayCollection();
        $this->etiquettes = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validerProprietaire(ExecutionContextInterface $context): void
    {
        if (null !== $this->proprietaire && $this->proprietaire->estMineur()) {
            $context->buildViolation('Un membre mineur ne peut pas créer de logement.')
                ->atPath('proprietaire')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, MembreLogement>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(MembreLogement $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setLogement($this);
        }

        return $this;
    }

    public function removeMembre(MembreLogement $membre): static
    {
        $this->membres->removeElement($membre);

        return $this;
    }

    /**
     * @return Collection<int, Piece>
     */
    public function getPieces(): Collection
    {
        return $this->pieces;
    }

    public function addPiece(Piece $piece): static
    {
        if (!$this->pieces->contains($piece)) {
            $this->pieces->add($piece);
            $piece->setLogement($this);
        }

        return $this;
    }

    public function removePiece(Piece $piece): static
    {
        $this->pieces->removeElement($piece);

        return $this;
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
            $etiquette->setLogement($this);
        }

        return $this;
    }

    public function removeEtiquette(Etiquette $etiquette): static
    {
        $this->etiquettes->removeElement($etiquette);

        return $this;
    }
}
