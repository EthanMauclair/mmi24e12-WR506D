<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données envoyées par le formulaire d'inscription.
 */
final class InscriptionDto
{
    #[Assert\NotBlank(message: 'Choisissez un nom d\'utilisateur.')]
    #[Assert\Length(min: 3, max: 30)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9._-]+$/', message: 'Utilisez uniquement des lettres sans accent, des chiffres, des points, des tirets ou des tirets bas.')]
    public ?string $pseudo = null;

    #[Assert\NotBlank(message: 'Indiquez votre e-mail.')]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins 8 caractères.')]
    public ?string $motDePasse = null;

    #[Assert\NotBlank(message: 'Indiquez votre date de naissance.')]
    #[Assert\Date(message: 'Cette date n\'est pas valide.')]
    public ?string $dateNaissance = null;

    public bool $estPrive = false;

    #[Assert\IsTrue(message: 'Vous devez accepter les conditions d\'utilisation.')]
    public bool $conditionsAcceptees = false;

    #[Assert\Email]
    public ?string $emailTuteur = null;

    /**
     * @param array<string, mixed> $donnees
     */
    public static function depuisTableau(array $donnees): self
    {
        $dto = new self();
        $dto->pseudo = self::texte($donnees['pseudo'] ?? null);
        $dto->email = self::texte($donnees['email'] ?? null);
        $dto->motDePasse = \is_string($donnees['motDePasse'] ?? null) ? $donnees['motDePasse'] : null;
        $dto->dateNaissance = self::texte($donnees['dateNaissance'] ?? null);
        $dto->estPrive = true === ($donnees['estPrive'] ?? false);
        $dto->conditionsAcceptees = true === ($donnees['conditionsAcceptees'] ?? false);
        $dto->emailTuteur = self::texte($donnees['emailTuteur'] ?? null);

        return $dto;
    }

    private static function texte(mixed $valeur): ?string
    {
        if (!\is_string($valeur)) {
            return null;
        }

        $valeur = trim($valeur);

        return '' === $valeur ? null : $valeur;
    }
}
