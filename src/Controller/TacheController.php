<?php

namespace App\Controller;

use App\Entity\Tache;
use App\Repository\ListeRepository;
use App\Repository\TacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
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
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Service\VersioningService;

#[Route('/api/liste/{listeId}')]
#[IsGranted('IS_AUTHENTICATED_FULLY', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
class TacheController extends AbstractController
{
    /**
     * Obtient toutes les tâches appartenant à une liste spécifique.
     *
     * @param int $listeId
     * @param TacheRepository $tacheRepository
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/tache', name: 'TacheByListeId', methods: ['GET'])]
    public function GetTacheByListeId(int $listeId, TagAwareCacheInterface $cachePool, TacheRepository $tacheRepository,  SerializerInterface $serializer): JsonResponse
    {
        // Récupère toutes les tâches appartenant à une liste spécifique (listeId)
        $tache = $tacheRepository->findBy(['liste' => $listeId]);
        $idCache = "getAllTache";

        $jsonTache = $cachePool->get($idCache, function (ItemInterface $item) use ($tacheRepository, $serializer, $tache) {
            echo ("L'element n'est pas encore en cache");
            $item->tag("listeCache");
            $context = SerializationContext::create()->setGroups(["getTache"]);
            return $serializer->serialize($tache, 'json', $context);
        });
        // Sérialise les tâches en JSON
        return new JsonResponse($jsonTache, Response::HTTP_OK, [], true);
    }

    /**
     * Obtient une tâche par son ID.
     *
     * @param Tache $tache
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/tache/{id}', name: 'TacheById', methods: ['GET'])]
    public function GetTacheById(VersioningService $versioningService, Tache $tache,  SerializerInterface $serializer): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(["getTache"]);
        $context->setVersion($version);
        $jsonTache = $serializer->serialize($tache, 'json',  $context);
        return new JsonResponse($jsonTache, Response::HTTP_OK, [], true);
    }

    /**
     * Crée une nouvelle tâche associée à une liste spécifique.
     *
     * @param ValidatorInterface $validator
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ListeRepository $listeRepository
     * @param int $listeId
     * @return JsonResponse
     */
    #[Route('/tache', name: 'createTache', methods: ['POST'])]
    public function createTache(TagAwareCacheInterface $cache, ValidatorInterface $validator, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ListeRepository $listeRepository, int $listeId): JsonResponse
    {
        // Désérialise la tâche à partir des données JSON de la requête
        $tache = $serializer->deserialize($request->getContent(), Tache::class, 'json');
        // Vérifie les erreurs de validation
        $errors = $validator->validate($tache);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // Récupère la liste associée en utilisant $listeId fourni
        $liste = $listeRepository->find($listeId);
        if (!$liste) {
            // Gère le cas où la liste avec l'ID donné n'existe pas
            return new JsonResponse(['error' => 'Liste non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $tache->setListe($liste);

        $em->persist($tache);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);
        $context = SerializationContext::create()->setGroups(["getTache"]);
        $jsonTache = $serializer->serialize($tache, 'json',  $context);

        $location = $urlGenerator->generate('TacheById', ['listeId' => $listeId, 'id' => $tache->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonTache, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Met à jour une tâche existante.
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Tache $currentTache
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/tache/{id}', name: 'updateTache', methods: ['PATCH'])]
    public function updateTache(
        TagAwareCacheInterface $cache,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ListeRepository $listeRepository,
        Tache $currentTache,
        EntityManagerInterface $em
    ): JsonResponse {

        $newTache = $serializer->deserialize($request->getContent(), Tache::class, 'json');
        $currentTache->setTitre($newTache->getTitre());
        $currentTache->setStatus(false);
        $currentTache->setContent($newTache->getContent());
        // On vérifie les erreurs
        $errors = $validator->validate($currentTache);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }


        $idListe = $newTache->getUser()->getId() ?? -1;
        $currentTache->setListe($listeRepository->find($idListe));

        $em->persist($currentTache);
        $em->flush();
        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Supprime une tâche.
     *
     * @param Tache $tache
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route('/tache/{id}', name: 'deleteTache', methods: ['DELETE'])]
    public function deleteTache(TagAwareCacheInterface $cache, Tache $tache, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($tache);
        $em->flush();
        // On vide le cache. 
        $cache->invalidateTags(["listeCache"]);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
