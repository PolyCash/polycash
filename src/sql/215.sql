ALTER TABLE `events` ADD `season_index` INT NULL DEFAULT NULL AFTER `event_index`;
ALTER TABLE `events` ADD INDEX (`game_id`, `season_index`);
ALTER TABLE `game_defined_events` ADD `season_index` INT NULL DEFAULT NULL AFTER `event_index`;
ALTER TABLE `games` ADD `target_option_block_score` INT NULL DEFAULT NULL AFTER `order_options_by`;
ALTER TABLE `options` ADD `target_score` FLOAT NULL DEFAULT NULL AFTER `option_block_score`;
