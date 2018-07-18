ALTER TABLE `game_defined_events` ADD `espn_event_uid` VARCHAR(64) NULL DEFAULT NULL AFTER `outcome_index`;
ALTER TABLE `game_defined_options` ADD `target_probability` FLOAT NULL DEFAULT NULL AFTER `name`;
ALTER TABLE `blocks` ADD INDEX (`blockchain_id`, `time_mined`);