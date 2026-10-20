<?php

namespace App\Entity;

use App\Enum\Saison;
use App\Repository\DetailVetementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DetailVetementRepository::class)]
class DetailVetement
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Marque $marque = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Couleur $couleurPrincipale = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Couleur $couleurSecondaire = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $taille = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $matiere = null;

    #[ORM\Column(enumType: Saison::class, options: ['default' => 'TOUTES'])]
    private Saison $saison = Saison::TOUTES;

    #[ORM\Column(options: ['default' => false])]
    private bool $aCapuche = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $estImpermeable = false;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 3])]
    #[Assert\Range(min: 1, max: 5)]
    private int $niveauChaleur = 3;

    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getMarque(): ?Marque
    {
        return $this->marque;
    }

    public function setMarque(?Marque $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getCouleurPrincipale(): ?Couleur
    {
        return $this->couleurPrincipale;
    }

    public function setCouleurPrincipale(?Couleur $couleurPrincipale): static
    {
        $this->couleurPrincipale = $couleurPrincipale;

        return $this;
    }

    public function getCouleurSecondaire(): ?Couleur
    {
        return $this->couleurSecondaire;
    }

    public function setCouleurSecondaire(?Couleur $couleurSecondaire): static
    {
        $this->couleurSecondaire = $couleurSecondaire;

        return $this;
    }

    public function getTaille(): ?string
    {
        return $this->taille;
    }

    public function setTaille(?string $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function getMatiere(): ?string
    {
        return $this->matiere;
    }

    public function setMatiere(?string $matiere): static
    {
        $this->matiere = $matiere;

        return $this;
    }

    public function getSaison(): Saison
    {
        return $this->saison;
    }

    public function setSaison(Saison $saison): static
    {
        $this->saison = $saison;

        return $this;
    }

    public function isACapuche(): bool
    {
        return $this->aCapuche;
    }

    public function setACapuche(bool $aCapuche): static
    {
        $this->aCapuche = $aCapuche;

        return $this;
    }

    public function isEstImpermeable(): bool
    {
        return $this->estImpermeable;
    }

    public function setEstImpermeable(bool $estImpermeable): static
    {
        $this->estImpermeable = $estImpermeable;

        return $this;
    }

    public function getNiveauChaleur(): int
    {
        return $this->niveauChaleur;
    }

    public function setNiveauChaleur(int $niveauChaleur): static
    {
        $this->niveauChaleur = $niveauChaleur;

        return $this;
    }
}
