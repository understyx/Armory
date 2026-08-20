<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wow_items table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wow_items (
            item_id BIGINT PRIMARY KEY NOT NULL,
            name VARCHAR(96) DEFAULT NULL,
            item_level INTEGER DEFAULT NULL,
            quality INTEGER DEFAULT NULL,
            type INTEGER DEFAULT NULL,
            requires INTEGER DEFAULT NULL,
            class INTEGER DEFAULT NULL,
            subclass INTEGER DEFAULT NULL,
            gem_slots INTEGER DEFAULT NULL,
            gear_score INTEGER DEFAULT NULL,
            icon VARCHAR(64) DEFAULT NULL
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wow_items');
    }
}
