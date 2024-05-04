<?php

namespace App\Controller;

use App\Entity\Liste;
use App\Entity\User;
use App\Repository\ListeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api')]
#[IsGranted('IS_AUTHENTICATED_FULLY', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
class ListeController extends AbstractController
{
    /**
     * Obtient toutes les listes associées à l'utilisateur authentifié.
     *
     * @param Security $security
     * @param ListeRepository $listeRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/liste', name: 'AllListe', methods: ['GET'])]
    public function GetAllListe(Security $security, ListeRepository $listeRepository,  SerializerInterface $serializer, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $user = $security->getUser();
        // Obtient l'utilisateur authentifié
        $idCache = "getAllListe-";
        $jsonListe = $cachePool->get($idCache, function (ItemInterface $item) use ($listeRepository, $serializer, $user) {
            echo ("L'element n'est pas encore en cache");
            $item->tag("listeCache");
            $listeAll = $listeRepository->findBy(['user' => $user]);
            $context = SerializationContext::create()->setGroups(["getTache"]);
            return $serializer->serialize($listeAll, 'json', $context);
        });

        // Récupère les listes associées à l'utilisateur authentifié
        return new JsonResponse($jsonListe, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Méthode temporaire pour vider le cache. 
     *
     * @param TagAwareCacheInterface $cache
     * @return void
     */
    #[Route('liste/clearCache', name: "clearCache", methods: ['GET'])]
    public function clearCache(TagAwareCacheInterface $cache)
    {
        $cache->invalidateTags(["listeCache"]);
        return new JsonResponse("Cache Vidé", JsonResponse::HTTP_OK);
    }

    /**
     * Obtient une liste par son ID.
     *
     * @param SerializerInterface $serializer
     * @param Liste $liste
     * @return JsonResponse
     */
    #[Route('/liste/{id}', name: 'ListeById', methods: ['GET'])]
    public function GetListeById(SerializerInterface $serializer, Liste $liste): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getTache"]);
        $jsonListe = $serializer->serialize($liste, 'json', $context);
        return new JsonResponse($jsonListe, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Crée une nouvelle liste pour l'utilisateur authentifié.
     *
     * @param Security $security
     * @param ValidatorInterface $validator
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @return JsonResponse
     */
    #[Route('/liste', name: "createListe", methods: ['POST'])]
    public function createListe(Security $security, ValidatorInterface $validator, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, TagAwareCacheInterface $cache): JsonResponse
    {
        $user = $security->getUser();

        // if (!$user) {
        //     return new JsonResponse(['message' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        // }

        // Crée une nouvelle instance de Liste
        $liste = $serializer->deserialize($request->getContent(), Liste::class, 'json');

        // Associe l'utilisateur authentifié à la Liste
        $liste->setUser($user);

        // Vérifie les erreurs de validation
        $errors = $validator->validate($liste);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($liste);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);

        $context = SerializationContext::create()->setGroups(["getTache"]);
        $jsonListe = $serializer->serialize($liste, 'json', $context);

        $location = $urlGenerator->generate('ListeById', ['id' => $liste->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonListe, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Met à jour une liste existante.
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Liste $currentListe
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/liste/{id}', name: "updateListe", methods: ['PATCH'])]
    public function updateListe(
        Request $request,
        SerializerInterface $serializer,
        Liste $currentListe,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $newListe = $serializer->deserialize($request->getContent(), Liste::class, 'json');
        $currentListe->setTitre($newListe->getTitre());

        // On vérifie les erreurs
        $errors = $validator->validate($currentListe);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }


        $idUser = $newListe->getUser()->getId() ?? -1;
        $currentListe->setUser($userRepository->find($idUser));

        $em->persist($currentListe);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Supprime une liste.
     *
     * @param Liste $liste
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/liste/{id}', name: 'deleteListe', methods: ['DELETE'])]
    public function deleteListe(Liste $liste, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $em->remove($liste);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
