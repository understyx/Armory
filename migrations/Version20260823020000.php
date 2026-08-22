<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store per-character update claims for the five-minute refresh limit';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('character_update_requests');
        $table->addColumn('name', Types::STRING, ['length' => 64]);
        $table->addColumn('realm', Types::STRING, ['length' => 64]);
        $table->addColumn('requested_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('claim_token', Types::STRING, ['length' => 32, 'fixed' => true]);
        $table->setPrimaryKey(['name', 'realm']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('character_update_requests');
    }
}
