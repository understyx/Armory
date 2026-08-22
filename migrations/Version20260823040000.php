<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cache guild rosters and Uwu-logs ranks with daily and 30-minute request throttles';
    }

    public function up(Schema $schema): void
    {
        $guilds = $schema->createTable('guild_snapshots');
        $guilds->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $guilds->addColumn('name', Types::STRING, ['length' => 128]);
        $guilds->addColumn('realm', Types::STRING, ['length' => 64]);
        $guilds->addColumn('faction', Types::STRING, ['length' => 16, 'notnull' => false]);
        $guilds->addColumn('member_count', Types::INTEGER);
        $guilds->addColumn('pve_points', Types::INTEGER);
        $guilds->addColumn('members', Types::JSON);
        $guilds->addColumn('scraped_at', Types::DATETIME_IMMUTABLE);
        $guilds->setPrimaryKey(['id']);
        $guilds->addUniqueIndex(['name', 'realm'], 'UNIQ_GUILD_REALM');

        $ranks = $schema->createTable('uwu_log_ranks');
        $ranks->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $ranks->addColumn('name', Types::STRING, ['length' => 64]);
        $ranks->addColumn('realm', Types::STRING, ['length' => 64]);
        $ranks->addColumn('spec', Types::STRING, ['length' => 1]);
        $ranks->addColumn('overall_rank', Types::INTEGER);
        $ranks->addColumn('payload', Types::JSON);
        $ranks->addColumn('scraped_at', Types::DATETIME_IMMUTABLE);
        $ranks->setPrimaryKey(['id']);
        $ranks->addUniqueIndex(['name', 'realm', 'spec'], 'UNIQ_UWU_CHARACTER_SPEC');
        $ranks->addIndex(['name', 'realm', 'overall_rank'], 'IDX_UWU_BEST_RANK');

        $guildRequests = $schema->createTable('guild_update_requests');
        $guildRequests->addColumn('name', Types::STRING, ['length' => 128]);
        $guildRequests->addColumn('realm', Types::STRING, ['length' => 64]);
        $guildRequests->addColumn('requested_on', Types::DATE_IMMUTABLE);
        $guildRequests->addColumn('requested_at', Types::DATETIME_IMMUTABLE);
        $guildRequests->setPrimaryKey(['name', 'realm', 'requested_on']);

        $uwuRequests = $schema->createTable('uwu_log_update_requests');
        $uwuRequests->addColumn('name', Types::STRING, ['length' => 64]);
        $uwuRequests->addColumn('realm', Types::STRING, ['length' => 64]);
        $uwuRequests->addColumn('requested_at', Types::DATETIME_IMMUTABLE);
        $uwuRequests->addColumn('claim_token', Types::STRING, ['length' => 32, 'fixed' => true]);
        $uwuRequests->setPrimaryKey(['name', 'realm']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('uwu_log_update_requests');
        $schema->dropTable('guild_update_requests');
        $schema->dropTable('uwu_log_ranks');
        $schema->dropTable('guild_snapshots');
    }
}
