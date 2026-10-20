<?php

namespace App\Entity;

use App\Enum\StatutAbonnement;
use App\Repository\AbonnementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AbonnementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_abonnement', columns: ['abonne_id', 'suivi_id'])]
#[UniqueEntity(fields: ['abonne', 'suivi'], message: 'Cet abonnement existe déjà.')]
class Abonnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $abonne = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Utilisateur $suivi = null;

    #[ORM\Column(enumType: StatutAbonnement::class)]
    #[Assert\NotNull]
    private ?StatutAbonnement $statut = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $estAmi = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $accepteAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function statutInitialPour(Utilisateur $suivi): StatutAbonnement
    {
        return $suivi->isEstPrive() ? StatutAbonnement::EN_ATTENTE : StatutAbonnement::ACCEPTE;
    }

    public function accepter(): static
    {
        $this->statut = StatutAbonnement::ACCEPTE;
        $this->accepteAt = new \DateTimeImmutable();

        return $this;
    }

    #[Assert\Callback]
    public function validerAbonnement(ExecutionContextInterface $context): void
    {
        if (null !== $this->abonne && $this->abonne === $this->suivi) {
            $context->buildViolation('Un membre ne peut pas s\'abonner à lui-même.')
                ->atPath('suivi')
                ->addViolation();
        }

        if ($this->estAmi && StatutAbonnement::ACCEPTE !== $this->statut) {
            $context->buildViolation('Seul un abonné accepté peut être choisi comme ami.')
                ->atPath('estAmi')
                ->addViolation();
        }

        if (null === $this->id && null !== $this->suivi && null !== $this->statut
            && self::statutInitialPour($this->suivi) !== $this->statut) {
            $context->buildViolation('Un abonnement à un profil privé commence en attente, sinon il est accepté.')
                ->atPath('statut')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAbonne(): ?Utilisateur
    {
        return $this->abonne;
    }

    public function setAbonne(?Utilisateur $abonne): static
    {
        $this->abonne = $abonne;

        return $this;
    }

    public function getSuivi(): ?Utilisateur
    {
        return $this->suivi;
    }

    /**
     * Fixe aussi le statut initial s'il n'est pas encore défini.
     */
    public function setSuivi(?Utilisateur $suivi): static
    {
        $this->suivi = $suivi;

        if (null !== $suivi && null === $this->statut) {
            $this->statut = self::statutInitialPour($suivi);
            if (StatutAbonnement::ACCEPTE === $this->statut) {
                $this->accepteAt = new \DateTimeImmutable();
            }
        }

        return $this;
    }

    public function getStatut(): ?StatutAbonnement
    {
        return $this->statut;
    }

    public function setStatut(StatutAbonnement $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isEstAmi(): bool
    {
        return $this->estAmi;
    }

    public function setEstAmi(bool $estAmi): static
    {
        $this->estAmi = $estAmi;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAccepteAt(): ?\DateTimeImmutable
    {
        return $this->accepteAt;
    }

    public function setAccepteAt(?\DateTimeImmutable $accepteAt): static
    {
        $this->accepteAt = $accepteAt;

        return $this;
    }
}
