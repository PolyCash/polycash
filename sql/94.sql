ALTER TABLE `events` ADD `event_starting_time` DATETIME NULL DEFAULT NULL AFTER `event_final_block`, ADD `event_final_time` DATETIME NULL DEFAULT NULL AFTER `event_starting_time`;
ALTER TABLE `game_defined_events` ADD `event_starting_time` DATETIME NULL DEFAULT NULL AFTER `event_final_block`, ADD `event_final_time` DATETIME NULL DEFAULT NULL AFTER `event_starting_time`;
ALTER TABLE `events` ADD `event_payout_offset_time` TIME NULL DEFAULT NULL AFTER `event_payout_block`;
ALTER TABLE `game_defined_events` ADD `event_payout_offset_time` TIME NULL DEFAULT NULL AFTER `event_payout_block`;