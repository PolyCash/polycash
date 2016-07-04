ALTER TABLE `games` ADD `buyins_allowed` TINYINT(1) NOT NULL DEFAULT '0' AFTER `seconds_per_block`;
ALTER TABLE `game_types` ADD `buyins_allowed` TINYINT(1) NOT NULL DEFAULT '0' AFTER `payout_weight`;
