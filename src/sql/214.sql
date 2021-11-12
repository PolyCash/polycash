ALTER TABLE `events` CHANGE `event_outcome_block` `event_determined_to_block` INT(11) NULL DEFAULT NULL;
ALTER TABLE `events` ADD `event_determined_from_block` INT(11) NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `game_defined_events` CHANGE `event_outcome_block` `event_determined_to_block` INT(11) NULL DEFAULT NULL;
ALTER TABLE `game_defined_events` ADD `event_determined_from_block` INT(11) NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `events` ADD INDEX (`game_id`, `event_determined_from_block`);
