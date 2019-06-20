ALTER TABLE `games` ADD `cached_pending_bets` BIGINT(20) NULL DEFAULT NULL AFTER `coins_in_existence_block`;
ALTER TABLE `games` ADD `cached_vote_supply` BIGINT(20) NULL DEFAULT NULL AFTER `cached_pending_bets`;
ALTER TABLE `games` DROP `coins_in_existence_block`;
