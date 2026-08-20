<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014210506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow users without an email address';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP INDEX UNIQ_8D93D649E7927C74, MODIFY email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` MODIFY email VARCHAR(180) NOT NULL, ADD UNIQUE INDEX UNIQ_8D93D649E7927C74 (email)');
    }
}
