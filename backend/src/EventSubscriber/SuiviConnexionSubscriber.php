<?php

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use App\Security\VerificateurCompte;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Suivi des comptes pour le back office :
 * - la date de dernière connexion est enregistrée à chaque connexion (indicateur « membres actifs ») ;
 * - un membre bloqué alors qu'il est connecté est déconnecté dès sa requête suivante.
 */
final class SuiviConnexionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly TranslatorInterface $traducteur,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'surConnexion',
            // Après le pare-feu (priorité 8), qui a chargé l'utilisateur de la session
            KernelEvents::REQUEST => ['verifierBlocage', 0],
        ];
    }

    public function surConnexion(LoginSuccessEvent $event): void
    {
        $utilisateur = $event->getUser();
        if ($utilisateur instanceof Utilisateur) {
            $utilisateur->setDerniereConnexionAt(new \DateTimeImmutable());
            $this->em->flush();
        }
    }

    public function verifierBlocage(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $utilisateur = $this->security->getUser();
        if ($utilisateur instanceof Utilisateur && $utilisateur->isEstBloque()) {
            $this->security->logout(false);
            $event->setResponse(new JsonResponse(['message' => $this->traducteur->trans(VerificateurCompte::MESSAGE_BLOQUE)], Response::HTTP_UNAUTHORIZED));
        }
    }
}
