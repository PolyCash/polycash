ALTER TABLE `blocks` ADD `time_loaded` INT NULL DEFAULT NULL AFTER `time_created`;
ALTER TABLE `option_blocks` CHANGE `rand_bytes` `rand_chars` VARCHAR(10) NULL DEFAULT NULL;
ALTER TABLE `game_defined_events` ADD `next_event_index` INT NULL DEFAULT NULL AFTER `event_index`;
ALTER TABLE `events` ADD `next_event_index` INT NULL DEFAULT NULL AFTER `event_index`;
ALTER TABLE `events` ADD INDEX (`game_id`, `next_event_index`);
ALTER TABLE `game_types` ADD `event_type_name_plural` VARCHAR(50) NULL DEFAULT NULL AFTER `event_type_name`;
ALTER TABLE `games` ADD `event_type_name_plural` VARCHAR(50) NULL DEFAULT NULL AFTER `event_type_name`;