ALTER TABLE `game_defined_events` ADD `espn_event_uid` VARCHAR(64) NULL DEFAULT NULL AFTER `outcome_index`;
ALTER TABLE `game_defined_options` ADD `target_probability` FLOAT NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `blocks` ADD INDEX (`blockchain_id`, `time_mined`);
ALTER TABLE `transactions` CHANGE `OP_RETURN` `OP_RETURN` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
ALTER TABLE `events` ADD INDEX (`game_id`, `event_starting_block`);
ALTER TABLE `events` ADD INDEX (`game_id`, `event_final_block`);
ALTER TABLE `options` DROP INDEX `event_option_index`;
ALTER TABLE `options` ADD UNIQUE (`event_id`, `event_option_index`);
ALTER TABLE `entities` ADD UNIQUE (`entity_type_id`, `entity_name`);