<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add talent_trees_data column to character_snapshots table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE character_snapshots ADD talent_trees_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE character_snapshots DROP talent_trees_data');
    }
}
