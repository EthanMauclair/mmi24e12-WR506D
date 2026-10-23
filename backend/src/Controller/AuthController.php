<?php

namespace App\Controller;

use App\Api\Presentateur;
use App\Dto\InscriptionDto;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class AuthController extends ApiController
{
    public function __construct(private readonly Presentateur $presentateur)
    {
    }

    #[Route('/inscription', name: 'api_inscription', methods: ['POST'])]
    public function inscription(
        Request $request,
        ValidatorInterface $validator,
        UtilisateurRepository $utilisateurs,
        UserPasswordHasherInterface $hacheur,
        EntityManagerInterface $em,
        Security $security,
    ): JsonResponse {
        $dto = InscriptionDto::depuisTableau($this->lireJson($request));
        $messages = $this->messagesParChamp($validator->validate($dto));
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $utilisateur = (new Utilisateur())
            ->setPseudo($dto->pseudo)
            ->setEmail(mb_strtolower($dto->email))
            ->setDateNaissance(\DateTimeImmutable::createFromFormat('!Y-m-d', $dto->dateNaissance))
            ->setEstPrive($dto->estPrive)
            ->setMotDePasse('');

        // Un mineur est rattaché au compte majeur dont il donne l'e-mail
        if ($utilisateur->estMineur()) {
            if (null === $dto->emailTuteur) {
                return $this->reponseInvalide(['emailTuteur' => 'Indiquez l\'e-mail du compte majeur qui vous rattache.']);
            }
            $tuteur = $utilisateurs->findOneBy(['email' => mb_strtolower($dto->emailTuteur)]);
            if (null === $tuteur) {
                return $this->reponseInvalide(['emailTuteur' => 'Aucun compte ne correspond à cet e-mail.']);
            }
            $utilisateur->setTuteur($tuteur);
        }

        $messages = $this->messagesParChamp($validator->validate($utilisateur), ['tuteur' => 'emailTuteur']);
        if ([] !== $messages) {
            return $this->reponseInvalide($messages);
        }

        $utilisateur->setMotDePasse($hacheur->hashPassword($utilisateur, $dto->motDePasse));
        $em->persist($utilisateur);
        $em->flush();

        $security->login($utilisateur, 'json_login', 'main');

        return $this->json($this->presentateur->utilisateur($utilisateur), Response::HTTP_CREATED);
    }

    /**
     * Le pare-feu (json_login) vérifie l'e-mail et le mot de passe avant d'appeler cette méthode.
     */
    #[Route('/connexion', name: 'api_connexion', methods: ['POST'])]
    public function connexion(#[CurrentUser] ?Utilisateur $utilisateur): JsonResponse
    {
        if (null === $utilisateur) {
            return $this->json(['message' => 'E-mail ou mot de passe manquant.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->presentateur->utilisateur($utilisateur));
    }

    #[Route('/moi', name: 'api_moi', methods: ['GET'])]
    public function moi(#[CurrentUser] Utilisateur $utilisateur): JsonResponse
    {
        return $this->json($this->presentateur->utilisateur($utilisateur));
    }

    /**
     * Intercepté par le pare-feu (voir security.yaml et DeconnexionSubscriber).
     */
    #[Route('/deconnexion', name: 'api_deconnexion', methods: ['POST'])]
    public function deconnexion(): never
    {
        throw new \LogicException('Cette route est gérée par le pare-feu.');
    }
}
