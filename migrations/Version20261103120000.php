<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cache WotLK talent spell tooltip text';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wow_spell_tooltips (
            spell_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            spell_rank INTEGER DEFAULT NULL,
            description TEXT NOT NULL,
            source VARCHAR(64) NOT NULL,
            source_url VARCHAR(512) NOT NULL,
            fetched_at DATETIME NOT NULL,
            PRIMARY KEY(spell_id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wow_spell_tooltips');
    }
}
