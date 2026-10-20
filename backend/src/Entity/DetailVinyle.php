<?php

namespace App\Entity;

use App\Enum\FormatVinyle;
use App\Repository\DetailVinyleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DetailVinyleRepository::class)]
class DetailVinyle
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(length: 13, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{8,13}$/', message: 'Le code EAN doit contenir entre 8 et 13 chiffres.')]
    private ?string $ean = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $artiste = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $label = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Positive]
    private ?int $anneeSortie = null;

    #[ORM\Column(nullable: true, enumType: FormatVinyle::class)]
    private ?FormatVinyle $format = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    #[Assert\Positive]
    private int $nbDisques = 1;

    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): static
    {
        $this->ean = $ean;

        return $this;
    }

    public function getArtiste(): ?string
    {
        return $this->artiste;
    }

    public function setArtiste(?string $artiste): static
    {
        $this->artiste = $artiste;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getAnneeSortie(): ?int
    {
        return $this->anneeSortie;
    }

    public function setAnneeSortie(?int $anneeSortie): static
    {
        $this->anneeSortie = $anneeSortie;

        return $this;
    }

    public function getFormat(): ?FormatVinyle
    {
        return $this->format;
    }

    public function setFormat(?FormatVinyle $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getNbDisques(): int
    {
        return $this->nbDisques;
    }

    public function setNbDisques(int $nbDisques): static
    {
        $this->nbDisques = $nbDisques;

        return $this;
    }
}
