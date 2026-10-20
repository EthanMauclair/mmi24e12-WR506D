<?php

namespace App\Entity;

use App\Repository\PretRepository;
use App\Validator\UnSeulPretEnCours;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PretRepository::class)]
#[UnSeulPretEnCours]
class Pret
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $preteur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Utilisateur $emprunteur = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $emprunteurNom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $datePret = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'datePret', message: 'La date de retour prévue ne peut pas précéder la date de prêt.')]
    private ?\DateTimeImmutable $dateRetourPrevue = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'datePret', message: 'La date de retour ne peut pas précéder la date de prêt.')]
    private ?\DateTimeImmutable $dateRetourReelle = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $note = null;

    public function estEnCours(): bool
    {
        return null === $this->dateRetourReelle;
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

    public function getPreteur(): ?Utilisateur
    {
        return $this->preteur;
    }

    public function setPreteur(?Utilisateur $preteur): static
    {
        $this->preteur = $preteur;

        return $this;
    }

    public function getEmprunteur(): ?Utilisateur
    {
        return $this->emprunteur;
    }

    /**
     * Renseigne aussi emprunteurNom avec le pseudo du membre s'il est vide.
     */
    public function setEmprunteur(?Utilisateur $emprunteur): static
    {
        $this->emprunteur = $emprunteur;

        if (null !== $emprunteur && null === $this->emprunteurNom) {
            $this->emprunteurNom = $emprunteur->getPseudo();
        }

        return $this;
    }

    public function getEmprunteurNom(): ?string
    {
        return $this->emprunteurNom;
    }

    public function setEmprunteurNom(string $emprunteurNom): static
    {
        $this->emprunteurNom = $emprunteurNom;

        return $this;
    }

    public function getDatePret(): ?\DateTimeImmutable
    {
        return $this->datePret;
    }

    public function setDatePret(\DateTimeImmutable $datePret): static
    {
        $this->datePret = $datePret;

        return $this;
    }

    public function getDateRetourPrevue(): ?\DateTimeImmutable
    {
        return $this->dateRetourPrevue;
    }

    public function setDateRetourPrevue(?\DateTimeImmutable $dateRetourPrevue): static
    {
        $this->dateRetourPrevue = $dateRetourPrevue;

        return $this;
    }

    public function getDateRetourReelle(): ?\DateTimeImmutable
    {
        return $this->dateRetourReelle;
    }

    public function setDateRetourReelle(?\DateTimeImmutable $dateRetourReelle): static
    {
        $this->dateRetourReelle = $dateRetourReelle;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
