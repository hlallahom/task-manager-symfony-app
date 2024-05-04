<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api')]
class UserController extends AbstractController
{
    /**
     * Inscription d'un nouvel utilisateur.
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param Request $request
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param JWTTokenManagerInterface $JWTManager
     * @return JsonResponse
     */
    #[Route('/signup', name: 'signup', methods: ['POST'])]
    public function signup(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        JWTTokenManagerInterface $JWTManager,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        // Désérialise la requête JSON en un objet User
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // Récupère l'email et le mot de passe de l'objet User
        $email = $user->getEmail();
        $password = $user->getPassword();
        // Vérifie si l'email ou le mot de passe est vide
        if (empty($email) || empty($password)) {
            return new JsonResponse(['message' => 'Email et mot de passe sont nécessaires'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifie si l'utilisateur avec l'email fourni existe déjà
        $existingUser = $userRepository->findOneByEmail($email);

        if ($existingUser) {
            return new JsonResponse(['message' => 'Email existe déja'], Response::HTTP_BAD_REQUEST);
        }

        // Crée une nouvelle entité User
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($userPasswordHasher->hashPassword($user, $password));


        // Persiste et enregistre l'entité User dans la base de données
        $em->persist($user);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);
        // Génère un jeton JWT pour le nouvel utilisateur
        $token = $JWTManager->create($user);

        return new JsonResponse(['token' => $token], Response::HTTP_CREATED);
    }

    /**
     * Connexion de l'utilisateur.
     *
     * @param JWTTokenManagerInterface $JWTManager
     * @param Request $request
     * @param RefreshTokenManagerInterface $refreshTokenManager
     * @param UserRepository $userRepository
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @return JsonResponse
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        JWTTokenManagerInterface $JWTManager,
        Request $request,
        // RefreshTokenManagerInterface $refreshTokenManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $userPasswordHasher
    ) {
        // Obtient les identifiants de l'utilisateur depuis la requête (par exemple, email et mot de passe)
        $credentials = json_decode($request->getContent(), true);
        $email = $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;

        // Vérifie si l'email ou le mot de passe est manquant
        if (empty($email) || empty($password)) {
            return new JsonResponse(['message' => 'Email et mot de passe sont nécessaires'], Response::HTTP_BAD_REQUEST);
        }

        // Trouve l'utilisateur par email
        $user = $userRepository->findOneByEmail($email);

        // Vérifie si un utilisateur avec l'email fourni existe
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifie si le mot de passe fourni est correct
        if (!$userPasswordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // Génère un jeton JWT pour l'utilisateur authentifié
        $token = $JWTManager->create($user);

        return new JsonResponse(['token' => $token], Response::HTTP_CREATED);
    }


    /**
     * Obtient un jeton JWT pour l'utilisateur actuellement authentifié.
     *
     * @param Security $security
     * @param JWTTokenManagerInterface $JWTManager
     * @return JsonResponse
     */
    #[Route('/token', name: 'token', methods: ['GET'])]
    public function getTokenUser(Security $security, JWTTokenManagerInterface $JWTManager)
    {
        $user = $security->getUser();

        return new JsonResponse(['token' => $JWTManager->create($user)]);
    }

    /**
     * Liste tous les utilisateurs (réservé aux administrateurs).
     *
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/users', name: 'list_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function listUsers(SerializerInterface $serializer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $userRepository = $entityManager->getRepository(User::class);

        $idCache = "getAllUser-";

        $user = $userRepository->findAll();

        $jsonListe = $cache->get($idCache, function (ItemInterface $item) use ($user, $serializer) {
            echo ("L'element n'est pas encore en cache");
            $item->tag("listeCache");
            $context = SerializationContext::create()->setGroups(["getTache"]);
            return $serializer->serialize($user, 'json', $context);
        });

        return new JsonResponse(['users' => $jsonListe]);
    }

    /**
     * Cette méthode permet de récupérer un auteur en particulier en fonction de son id. 
     *
     * @param User $author
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/users/{id}', name: 'detailUser', methods: ['GET'])]
    public function getDetailUser(User $user, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getTache"]);
        $jsonUser = $serializer->serialize($user, 'json', $context);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
