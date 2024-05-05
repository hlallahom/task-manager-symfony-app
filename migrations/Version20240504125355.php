<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231218175355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is modified to set a default value of 0 (false)
        $this->addSql('CREATE TABLE `liste` (
            `id` PRIMARY KEY NOT NULL AUTO_INCREMENT,
            `user_id` int DEFAULT NULL,
            `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

        $this->addSql('CREATE TABLE `refresh_tokens` (
            `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
            `refresh_token` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
            `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `valid` datetime NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
        
        $this->addSql('CREATE TABLE `tache` (
            `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
            `liste_id` int NOT NULL,
            `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` tinyint(1) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
        
        $this->addSql('CREATE TABLE `user` (
            `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
            `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
            `roles` json NOT NULL,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is modified to set the default value to NULL
        $this->addSql('ALTER TABLE tache CHANGE status status BOOLEAN DEFAULT 1');
    }
}
