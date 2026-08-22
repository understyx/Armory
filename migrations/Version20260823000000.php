<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correct the reversed gender enum in cached character model data';
    }

    public function up(Schema $schema): void
    {
        $this->flipCharacterModelGender();
    }

    public function down(Schema $schema): void
    {
        $this->flipCharacterModelGender();
    }

    private function flipCharacterModelGender(): void
    {
        $this->addSql(<<<'SQL'
            UPDATE character_snapshots
            SET character_model = JSON_SET(
                character_model,
                '$.gender',
                1 - CAST(JSON_UNQUOTE(JSON_EXTRACT(character_model, '$.gender')) AS UNSIGNED)
            )
            WHERE character_model IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(character_model, '$.gender')) IN ('0', '1')
            SQL);
    }
}
