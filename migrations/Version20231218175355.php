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
        $this->addSql('ALTER TABLE tache CHANGE status status BOOLEAN DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is modified to set the default value to NULL
        $this->addSql('ALTER TABLE tache CHANGE status status BOOLEAN DEFAULT 1');
    }
}
