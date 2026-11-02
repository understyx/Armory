<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move pending Uwu-logs rank refresh messages to the uwu Messenger queue';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['messenger_messages'])) {
            return;
        }

        $this->addSql("UPDATE messenger_messages SET queue_name = 'uwu' WHERE queue_name = 'default' AND delivered_at IS NULL AND body LIKE '%RefreshUwuRankMessage%'");
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['messenger_messages'])) {
            return;
        }

        $this->addSql("UPDATE messenger_messages SET queue_name = 'default' WHERE queue_name = 'uwu' AND delivered_at IS NULL AND body LIKE '%RefreshUwuRankMessage%'");
    }
}
