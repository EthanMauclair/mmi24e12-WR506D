<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ConnexionEchoueeHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly TranslatorInterface $traducteur)
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['message' => $this->traducteur->trans('Trop de tentatives de connexion. Réessayez dans quelques minutes.')],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        if ($exception instanceof CustomUserMessageAccountStatusException) {
            return new JsonResponse(['message' => $this->traducteur->trans($exception->getMessageKey())], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['message' => $this->traducteur->trans('E-mail ou mot de passe incorrect.')], Response::HTTP_UNAUTHORIZED);
    }
}
