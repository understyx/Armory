<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cache earned raid achievement IDs and dates on character snapshots';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('character_snapshots')->addColumn('raid_achievements', Types::JSON, [
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('character_snapshots')->dropColumn('raid_achievements');
    }
}
