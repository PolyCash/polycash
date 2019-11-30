ALTER TABLE `events` ADD `event_outcome_block` INT NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `game_defined_events` ADD `event_outcome_block` INT NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `events` ADD INDEX (`game_id`, `event_payout_block`);
ALTER TABLE `events` ADD INDEX (`game_id`, `event_outcome_block`);
