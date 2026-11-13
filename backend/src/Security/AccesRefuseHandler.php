<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AccesRefuseHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private readonly TranslatorInterface $traducteur)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        return new JsonResponse(['message' => $this->traducteur->trans('Vous n\'avez pas accès à cette ressource.')], Response::HTTP_FORBIDDEN);
    }
}
