ALTER TABLE `events` ADD `payout_rate` FLOAT NULL DEFAULT '1' AFTER `payout_rule`;
ALTER TABLE `game_defined_events` ADD `payout_rate` FLOAT NULL DEFAULT '1' AFTER `payout_rule`;
ALTER TABLE `games` ADD `default_payout_rate` FLOAT NULL DEFAULT '1' AFTER `default_payout_rule`;
ALTER TABLE `games` ADD `min_payout_rate` FLOAT NULL DEFAULT NULL AFTER `max_option_index`, ADD `max_payout_rate` FLOAT NULL DEFAULT NULL AFTER `min_payout_rate`;