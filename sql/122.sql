ALTER TABLE `events` ADD `payout_rule` ENUM('binary','linear','') NOT NULL DEFAULT 'binary' AFTER `event_payout_block`;
ALTER TABLE `game_defined_events` ADD `payout_rule` ENUM('binary','linear','') NOT NULL DEFAULT 'binary' AFTER `event_payout_block`;
ALTER TABLE `games` ADD `default_payout_rule` ENUM('binary','linear','') NOT NULL DEFAULT 'binary' AFTER `send_round_notifications`;