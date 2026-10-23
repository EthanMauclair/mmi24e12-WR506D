<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Outils communs aux contrôleurs de l'API JSON.
 */
abstract class ApiController extends AbstractController
{
    /**
     * @return array<string, mixed>
     */
    protected function lireJson(Request $request): array
    {
        try {
            $donnees = $request->toArray();
        } catch (\Throwable) {
            throw new BadRequestHttpException('Le corps de la requête doit être un objet JSON.');
        }

        return $donnees;
    }

    /**
     * Regroupe les violations par champ (un message par champ).
     *
     * @param array<string, string> $correspondances renomme un chemin de propriété en nom de champ
     *
     * @return array<string, string>
     */
    protected function messagesParChamp(ConstraintViolationListInterface $violations, array $correspondances = []): array
    {
        $messages = [];
        foreach ($violations as $violation) {
            $chemin = $violation->getPropertyPath();
            $champ = $correspondances[$chemin] ?? ('' === $chemin ? 'general' : $chemin);
            $messages[$champ] ??= (string) $violation->getMessage();
        }

        return $messages;
    }

    /**
     * @param array<string, string> $messages
     */
    protected function reponseInvalide(array $messages): JsonResponse
    {
        return $this->json(
            ['message' => 'Certaines informations sont invalides.', 'violations' => $messages],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
