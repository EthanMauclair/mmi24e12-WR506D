<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse la connexion d'un membre bloqué par le super admin.
 */
final class VerificateurCompte implements UserCheckerInterface
{
    public const MESSAGE_BLOQUE = 'Votre compte a été bloqué par l\'administrateur du site.';

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Utilisateur && $user->isEstBloque()) {
            throw new CustomUserMessageAccountStatusException(self::MESSAGE_BLOQUE);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
