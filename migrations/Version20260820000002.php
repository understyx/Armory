<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create character_snapshots table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE character_snapshots (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(64) NOT NULL,
            realm VARCHAR(64) NOT NULL,
            level INTEGER NOT NULL,
            race VARCHAR(64) NOT NULL,
            class VARCHAR(64) NOT NULL,
            guild VARCHAR(128) DEFAULT NULL,
            gear_score INTEGER NOT NULL,
            avg_ilvl DOUBLE PRECISION NOT NULL,
            professions JSON NOT NULL,
            specializations JSON NOT NULL,
            equipped_items JSON NOT NULL,
            talent_strings JSON NOT NULL,
            glyphs JSON NOT NULL,
            enchants_status LONGTEXT NOT NULL,
            gems_status LONGTEXT NOT NULL,
            kill_stats JSON NOT NULL,
            scraped_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CHAR_REALM ON character_snapshots (name, realm)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE character_snapshots');
    }
}
