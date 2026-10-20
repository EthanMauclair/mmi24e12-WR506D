<?php

namespace App\Entity;

use App\Repository\CouleurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CouleurRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_couleur_nom', columns: ['nom'])]
#[UniqueEntity(fields: ['nom'], message: 'Cette couleur existe déjà.')]
class Couleur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private ?string $nom = null;

    #[ORM\Column(length: 7)]
    #[Assert\NotBlank]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG)]
    private ?string $codeHex = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCodeHex(): ?string
    {
        return $this->codeHex;
    }

    public function setCodeHex(string $codeHex): static
    {
        $this->codeHex = $codeHex;

        return $this;
    }
}
