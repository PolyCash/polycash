ALTER TABLE `game_definition_migrations` ADD INDEX (`game_id`, `migration_time`);
ALTER TABLE `game_definition_migrations` ADD INDEX (`migration_type`);
