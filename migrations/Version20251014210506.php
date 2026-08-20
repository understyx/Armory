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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, discord_id, discord_username FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) DEFAULT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , discord_id VARCHAR(255) DEFAULT NULL, discord_username VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO user (id, email, roles, discord_id, discord_username) SELECT id, email, roles, discord_id, discord_username FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64943349DE ON user (discord_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, discord_id, discord_username FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , discord_id VARCHAR(255) DEFAULT NULL, discord_username VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO "user" (id, email, roles, discord_id, discord_username) SELECT id, email, roles, discord_id, discord_username FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64943349DE ON "user" (discord_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
    }
}
