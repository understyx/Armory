<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the persistent lock_keys table used by Messenger deduplication';
    }

    public function up(Schema $schema): void
    {
        $lockSchema = new Schema();
        $table = $lockSchema->createTable('lock_keys');
        $table->addColumn('key_id', Types::STRING, ['length' => 64]);
        $table->addColumn('key_token', Types::STRING, ['length' => 44]);
        $table->addColumn('key_expiration', Types::INTEGER, ['unsigned' => true]);
        $table->setPrimaryKey(['key_id']);

        foreach ($lockSchema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lock_keys');
    }
}
