<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index character snapshot timestamps for the recently viewed homepage query';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE character_snapshots ADD INDEX IDX_CHAR_SCRAPED_AT (scraped_at), ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE character_snapshots DROP INDEX IDX_CHAR_SCRAPED_AT, ALGORITHM=INPLACE, LOCK=NONE');
    }
}
