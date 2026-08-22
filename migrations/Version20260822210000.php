<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cache externally enriched WotLK item effects, spell descriptions, and item-set data';
    }

    public function up(Schema $schema): void
    {
        $spells = $schema->createTable('wow_spells');
        $spells->addColumn('spell_id', Types::BIGINT);
        $spells->addColumn('locale', Types::STRING, ['length' => 8, 'default' => 'enUS']);
        $spells->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $spells->addColumn('description', Types::TEXT);
        $spells->addColumn('source', Types::STRING, ['length' => 32]);
        $spells->addColumn('source_url', Types::STRING, ['length' => 512, 'notnull' => false]);
        $spells->addColumn('fetched_at', Types::DATETIME_IMMUTABLE);
        $spells->setPrimaryKey(['spell_id', 'locale']);

        $effects = $schema->createTable('wow_item_effects');
        $effects->addColumn('id', Types::BIGINT, ['autoincrement' => true]);
        $effects->addColumn('item_id', Types::BIGINT);
        $effects->addColumn('position', Types::SMALLINT);
        $effects->addColumn('spell_id', Types::BIGINT, ['notnull' => false]);
        $effects->addColumn('trigger_type', Types::STRING, ['length' => 32]);
        $effects->addColumn('description', Types::TEXT);
        $effects->addColumn('source', Types::STRING, ['length' => 32]);
        $effects->addColumn('locale', Types::STRING, ['length' => 8, 'default' => 'enUS']);
        $effects->addColumn('fetched_at', Types::DATETIME_IMMUTABLE);
        $effects->setPrimaryKey(['id']);
        $effects->addUniqueIndex(['item_id', 'position', 'locale'], 'uniq_item_effect_position_locale');
        $effects->addIndex(['item_id', 'locale'], 'idx_item_effect_lookup');

        $sets = $schema->createTable('wow_item_sets');
        $sets->addColumn('set_id', Types::INTEGER);
        $sets->addColumn('name', Types::STRING, ['length' => 255]);
        $sets->addColumn('locale', Types::STRING, ['length' => 8, 'default' => 'enUS']);
        $sets->addColumn('source', Types::STRING, ['length' => 32]);
        $sets->addColumn('fetched_at', Types::DATETIME_IMMUTABLE);
        $sets->setPrimaryKey(['set_id', 'locale']);

        $members = $schema->createTable('wow_item_set_members');
        $members->addColumn('set_id', Types::INTEGER);
        $members->addColumn('item_id', Types::BIGINT);
        $members->addColumn('name', Types::STRING, ['length' => 255]);
        $members->addColumn('position', Types::SMALLINT);
        $members->addColumn('locale', Types::STRING, ['length' => 8, 'default' => 'enUS']);
        $members->setPrimaryKey(['set_id', 'item_id', 'locale']);
        $members->addIndex(['set_id', 'locale'], 'idx_item_set_member_lookup');

        $bonuses = $schema->createTable('wow_item_set_bonuses');
        $bonuses->addColumn('set_id', Types::INTEGER);
        $bonuses->addColumn('required_count', Types::SMALLINT);
        $bonuses->addColumn('position', Types::SMALLINT);
        $bonuses->addColumn('spell_id', Types::BIGINT, ['notnull' => false]);
        $bonuses->addColumn('description', Types::TEXT);
        $bonuses->addColumn('source', Types::STRING, ['length' => 32]);
        $bonuses->addColumn('locale', Types::STRING, ['length' => 8, 'default' => 'enUS']);
        $bonuses->setPrimaryKey(['set_id', 'required_count', 'position', 'locale']);
        $bonuses->addIndex(['set_id', 'locale'], 'idx_item_set_bonus_lookup');

        $cache = $schema->createTable('wow_external_tooltip_cache');
        $cache->addColumn('item_id', Types::BIGINT);
        $cache->addColumn('provider', Types::STRING, ['length' => 32]);
        $cache->addColumn('locale', Types::STRING, ['length' => 8, 'default' => 'enUS']);
        $cache->addColumn('status_code', Types::SMALLINT);
        $cache->addColumn('raw_response', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
        $cache->addColumn('parser_version', Types::STRING, ['length' => 16]);
        $cache->addColumn('error', Types::TEXT, ['notnull' => false]);
        $cache->addColumn('fetched_at', Types::DATETIME_IMMUTABLE);
        $cache->setPrimaryKey(['item_id', 'provider', 'locale']);
        $cache->addIndex(['item_id', 'locale', 'status_code'], 'idx_external_tooltip_state');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('wow_external_tooltip_cache');
        $schema->dropTable('wow_item_set_bonuses');
        $schema->dropTable('wow_item_set_members');
        $schema->dropTable('wow_item_sets');
        $schema->dropTable('wow_item_effects');
        $schema->dropTable('wow_spells');
    }
}
