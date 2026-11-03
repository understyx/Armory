<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track character refresh job status';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('character_refresh_status');
        $table->addColumn('name', Types::STRING, ['length' => 64]);
        $table->addColumn('realm', Types::STRING, ['length' => 64]);
        $table->addColumn('state', Types::STRING, ['length' => 32]);
        $table->addColumn('requested_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('started_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('finished_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->setPrimaryKey(['name', 'realm']);
        $table->addIndex(['state', 'requested_at'], 'IDX_REFRESH_STATE_REQUESTED');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('character_refresh_status');
    }
}
