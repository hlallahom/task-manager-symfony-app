<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240505092033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tache ADD content TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE `tache` ADD CONSTRAINT `FK_93872075E85441D8` FOREIGN KEY (`liste_id`) REFERENCES `liste` (`id`)');
        $this->addSql('ALTER TABLE `liste` ADD CONSTRAINT `FK_FCF22AF4A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)');
    }

    public function down(Schema $schema): void
    {
        //
    }
}
