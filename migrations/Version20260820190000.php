<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist character appearance and equipment display IDs for the 3D model viewer';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('character_snapshots')->addColumn('character_model', Types::JSON, [
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('character_snapshots')->dropColumn('character_model');
    }
}
