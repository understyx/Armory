<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store canonical TrinityCore tooltip data and GearScore provenance on wow_items';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('wow_items');
        $table->changeColumn('name', ['length' => 255]);
        $table->addColumn('tooltip_data', Types::JSON, ['notnull' => false]);
        $table->addColumn('source_build', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('gear_score_source', Types::STRING, ['length' => 32, 'notnull' => false]);
        $table->addColumn('gear_score_version', Types::STRING, ['length' => 32, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('wow_items');
        $table->changeColumn('name', ['length' => 96]);
        $table->dropColumn('tooltip_data');
        $table->dropColumn('source_build');
        $table->dropColumn('gear_score_source');
        $table->dropColumn('gear_score_version');
    }
}
