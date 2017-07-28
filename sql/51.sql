ALTER TABLE `options` ADD INDEX (`option_index`);
ALTER TABLE `options` ADD INDEX (`event_option_index`);
ALTER TABLE `event_outcomes` ADD `sum_score` BIGINT(20) NULL DEFAULT NULL AFTER `round_id`;
ALTER TABLE `games` ADD `loaded_until_block` INT NULL DEFAULT NULL AFTER `events_until_block`;