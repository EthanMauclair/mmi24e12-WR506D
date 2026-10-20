<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_pseudo', columns: ['pseudo'])]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce pseudo est déjà utilisé.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 30)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $prenom = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\LessThan('today')]
    private ?\DateTimeImmutable $dateNaissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $avatar = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $bio = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $estPrive = false;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Utilisateur $tuteur = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Logement>
     */
    #[ORM\OneToMany(targetEntity: Logement::class, mappedBy: 'proprietaire')]
    private Collection $logements;

    /**
     * @var Collection<int, MembreLogement>
     */
    #[ORM\OneToMany(targetEntity: MembreLogement::class, mappedBy: 'utilisateur')]
    private Collection $appartenances;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->logements = new ArrayCollection();
        $this->appartenances = new ArrayCollection();
    }

    public function estMineur(): bool
    {
        if (null === $this->dateNaissance) {
            return false;
        }

        return $this->dateNaissance > new \DateTimeImmutable('today -18 years');
    }

    #[Assert\Callback]
    public function validerTuteur(ExecutionContextInterface $context): void
    {
        if (null !== $this->tuteur && $this->tuteur === $this) {
            $context->buildViolation('Un membre ne peut pas être son propre tuteur.')
                ->atPath('tuteur')
                ->addViolation();

            return;
        }

        if (!$this->estMineur()) {
            return;
        }

        if (null === $this->tuteur) {
            $context->buildViolation('Un membre mineur doit être rattaché à un tuteur.')
                ->atPath('tuteur')
                ->addViolation();
        } elseif ($this->tuteur->estMineur()) {
            $context->buildViolation('Le tuteur doit être majeur.')
                ->atPath('tuteur')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, à supprimer lors du passage à Symfony 8
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function isEstPrive(): bool
    {
        return $this->estPrive;
    }

    public function setEstPrive(bool $estPrive): static
    {
        $this->estPrive = $estPrive;

        return $this;
    }

    public function getTuteur(): ?Utilisateur
    {
        return $this->tuteur;
    }

    public function setTuteur(?Utilisateur $tuteur): static
    {
        $this->tuteur = $tuteur;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Logement>
     */
    public function getLogements(): Collection
    {
        return $this->logements;
    }

    public function addLogement(Logement $logement): static
    {
        if (!$this->logements->contains($logement)) {
            $this->logements->add($logement);
            $logement->setProprietaire($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, MembreLogement>
     */
    public function getAppartenances(): Collection
    {
        return $this->appartenances;
    }

    public function addAppartenance(MembreLogement $appartenance): static
    {
        if (!$this->appartenances->contains($appartenance)) {
            $this->appartenances->add($appartenance);
            $appartenance->setUtilisateur($this);
        }

        return $this;
    }
}
