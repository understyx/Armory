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
        $this->addSql('ALTER TABLE character_snapshots ADD COLUMN talent_trees_data CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__character_snapshots AS SELECT id, name, realm, level, race, class, guild, gear_score, avg_ilvl, professions, specializations, equipped_items, talent_strings, glyphs, enchants_status, gems_status, kill_stats, scraped_at FROM character_snapshots');
        $this->addSql('DROP TABLE character_snapshots');
        $this->addSql('CREATE TABLE character_snapshots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(64) NOT NULL, realm VARCHAR(64) NOT NULL, level INTEGER NOT NULL, race VARCHAR(64) NOT NULL, class VARCHAR(64) NOT NULL, guild VARCHAR(128) DEFAULT NULL, gear_score INTEGER NOT NULL, avg_ilvl DOUBLE PRECISION NOT NULL, professions CLOB NOT NULL, specializations CLOB NOT NULL, equipped_items CLOB NOT NULL, talent_strings CLOB NOT NULL, glyphs CLOB NOT NULL, enchants_status CLOB NOT NULL, gems_status CLOB NOT NULL, kill_stats CLOB NOT NULL, scraped_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO character_snapshots (id, name, realm, level, race, class, guild, gear_score, avg_ilvl, professions, specializations, equipped_items, talent_strings, glyphs, enchants_status, gems_status, kill_stats, scraped_at) SELECT id, name, realm, level, race, class, guild, gear_score, avg_ilvl, professions, specializations, equipped_items, talent_strings, glyphs, enchants_status, gems_status, kill_stats, scraped_at FROM __temp__character_snapshots');
        $this->addSql('DROP TABLE __temp__character_snapshots');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CHAR_REALM ON character_snapshots (name, realm)');
    }
}
