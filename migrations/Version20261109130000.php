<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use Discord identity and add verified character linking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP COLUMN email, ADD discord_avatar_hash VARCHAR(255) DEFAULT NULL');
        $this->addSql("CREATE TABLE character_claims (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(64) NOT NULL, realm VARCHAR(64) NOT NULL, verified_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CHARACTER_CLAIM_NAME_REALM (name, realm), INDEX IDX_1DE1D02DA76ED395 (user_id), PRIMARY KEY(id), CONSTRAINT FK_1DE1D02DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE character_claim_challenges (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(64) NOT NULL, realm VARCHAR(64) NOT NULL, state VARCHAR(32) NOT NULL, required_slots JSON NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', scan_lease_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', state_revision INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_6CE4AB58A76ED395 (user_id), PRIMARY KEY(id), CONSTRAINT FK_6CE4AB58A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE character_claim_attempts (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_CHARACTER_CLAIM_ATTEMPT_USER_CREATED (user_id, created_at), PRIMARY KEY(id), CONSTRAINT FK_57DA7905A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE character_claim_attempts');
        $this->addSql('DROP TABLE character_claim_challenges');
        $this->addSql('DROP TABLE character_claims');
        $this->addSql('ALTER TABLE `user` DROP discord_avatar_hash, ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql("UPDATE `user` SET email = CONCAT('discord-', id, '@invalid.local') WHERE email IS NULL");
    }
}
