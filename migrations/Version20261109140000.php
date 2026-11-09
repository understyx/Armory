<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add character comments, Discord profile timestamps, and denormalized best linked character ranks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD last_login_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD best_character_name VARCHAR(64) DEFAULT NULL, ADD best_character_realm VARCHAR(64) DEFAULT NULL, ADD best_character_spec VARCHAR(64) DEFAULT NULL, ADD best_character_points DOUBLE PRECISION DEFAULT NULL, ADD best_character_rank INT DEFAULT NULL");
        $this->addSql("CREATE TABLE character_comments (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(64) NOT NULL, realm VARCHAR(64) NOT NULL, body VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_CHARACTER_COMMENT_NAME_REALM_CREATED (name, realm, created_at), INDEX IDX_CHARACTER_COMMENT_USER_CREATED (user_id, created_at), PRIMARY KEY(id), CONSTRAINT FK_CHARACTER_COMMENT_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE character_comments');
        $this->addSql('ALTER TABLE `user` DROP last_login_at, DROP best_character_name, DROP best_character_realm, DROP best_character_spec, DROP best_character_points, DROP best_character_rank');
    }
}
