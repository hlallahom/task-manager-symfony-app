<?php

namespace App\DataFixtures;

use App\Entity\Liste;
use App\Entity\Tache;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création d'un user "normal"
        $user = new User();
        $user->setEmail("demo@demo.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        // Création d'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail("admin@admin.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);

        // Création d listes 
        for ($i = 0; $i < 10; $i++) {
            $liste = new Liste;
            $liste->setTitre('Test Liste ' . $i);
            $liste->setUser($user);
            $manager->persist($liste);

            // Création de tâches pour chaque liste
            for ($j = 0; $j < 5; $j++) {
                $tache = new Tache();
                $tache->setTitre('Tâche ' . $j . ' de la liste ' . $i);
                $tache->setContent('Content ' . $i . ' de la Tâche ' . $j);
                $tache->setStatus(false);
                $tache->setListe($liste);
                $manager->persist($tache);
            }
        }

        $manager->flush();
    }
}
