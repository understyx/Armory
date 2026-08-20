<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pvp_stats and match_history columns to character_snapshots table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE character_snapshots ADD pvp_stats JSON DEFAULT NULL, ADD match_history JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE character_snapshots DROP COLUMN pvp_stats');
        $this->addSql('ALTER TABLE character_snapshots DROP COLUMN match_history');
    }
}
