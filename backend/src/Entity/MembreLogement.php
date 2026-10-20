<?php

namespace App\Entity;

use App\Enum\RoleLogement;
use App\Repository\MembreLogementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MembreLogementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_membre_logement', columns: ['logement_id', 'utilisateur_id'])]
#[UniqueEntity(fields: ['logement', 'utilisateur'], message: 'Ce membre fait déjà partie du logement.')]
class MembreLogement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Logement $logement = null;

    #[ORM\ManyToOne(inversedBy: 'appartenances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(enumType: RoleLogement::class)]
    #[Assert\NotNull]
    private ?RoleLogement $role = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validerRole(ExecutionContextInterface $context): void
    {
        if (null === $this->utilisateur || null === $this->role) {
            return;
        }

        if (!$this->utilisateur->estMineur()) {
            if (RoleLogement::MINEUR === $this->role) {
                $context->buildViolation('Un membre majeur ne peut pas avoir le rôle mineur.')
                    ->atPath('role')
                    ->addViolation();
            }

            return;
        }

        if (RoleLogement::MINEUR !== $this->role) {
            $context->buildViolation('Un membre mineur a forcément le rôle mineur.')
                ->atPath('role')
                ->addViolation();
        }

        $tuteur = $this->utilisateur->getTuteur();
        if (null === $tuteur || $this->logement?->getProprietaire() !== $tuteur) {
            $context->buildViolation('Un membre mineur ne peut rejoindre que le logement de son tuteur.')
                ->atPath('logement')
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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getRole(): ?RoleLogement
    {
        return $this->role;
    }

    public function setRole(RoleLogement $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
