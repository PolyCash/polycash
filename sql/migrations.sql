ALTER TABLE `games` ADD `url_identifier` VARCHAR(100) NULL DEFAULT NULL AFTER `creator_game_index`;
ALTER TABLE `cached_rounds` ADD `payout_transaction_id` INT(20) NULL DEFAULT NULL AFTER `payout_block_id`;
ALTER TABLE `cached_rounds` ADD UNIQUE (`payout_transaction_id`);
ALTER TABLE `invitations` ADD `sent_email_id` INT(20) NULL DEFAULT NULL AFTER `time_created`;
ALTER TABLE `user_messages` ADD `game_id` INT(20) NULL DEFAULT NULL AFTER `message_id`;
ALTER TABLE `user_messages` ADD `seen` TINYINT(1) NOT NULL DEFAULT '0' AFTER `message`;
ALTER TABLE `user_strategies` CHANGE `voting_strategy` `voting_strategy` ENUM('manual','by_rank','by_nation','by_plan','api','') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'manual';
ALTER TABLE `strategy_round_allocations` ADD `applied` TINYINT(1) NOT NULL DEFAULT '0' AFTER `points`;
ALTER TABLE `games` CHANGE `payout_weight` `payout_weight` ENUM('coin','coin_block','coin_round') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'coin_block';
ALTER TABLE `transactions` ADD `round_id` BIGINT(20) NULL DEFAULT NULL AFTER `block_id`;
ALTER TABLE `transactions` ADD `ref_round_id` BIGINT(20) NULL DEFAULT NULL AFTER `ref_coin_blocks_destroyed`, ADD `ref_coin_rounds_destroyed` BIGINT(20) NOT NULL DEFAULT '0' AFTER `ref_round_id`;
ALTER TABLE `game_nations` ADD `coin_round_score` BIGINT(20) NOT NULL DEFAULT '0' AFTER `coin_block_score`;
ALTER TABLE `game_nations` ADD `unconfirmed_coin_round_score` BIGINT(20) NOT NULL DEFAULT '0' AFTER `unconfirmed_coin_block_score`;
ALTER TABLE `transaction_IOs` ADD `create_round_id` BIGINT(20) NULL DEFAULT NULL AFTER `spend_block_id`, ADD `spend_round_id` BIGINT(20) NULL DEFAULT NULL AFTER `create_round_id`;
ALTER TABLE `transaction_IOs` ADD `coin_rounds_created` BIGINT(20) NULL DEFAULT NULL AFTER `coin_blocks_destroyed`, ADD `coin_rounds_destroyed` BIGINT(20) NULL DEFAULT NULL AFTER `coin_rounds_created`;
ALTER TABLE `invitations` ADD `giveaway_transaction_id` INT(20) NULL DEFAULT NULL AFTER `inviter_id`;
ALTER TABLE `games` ADD `inflation` ENUM( 'linear', 'exponential' ) NOT NULL DEFAULT 'linear' AFTER `block_timing` ;
ALTER TABLE `games` ADD `exponential_inflation_rate` DECIMAL( 9, 8 ) NOT NULL DEFAULT '0' AFTER `inflation` ;
ALTER TABLE `games` ADD `exponential_inflation_minershare` DECIMAL( 9, 8 ) NOT NULL DEFAULT '0' AFTER `inflation` ;
ALTER TABLE `games` ADD `initial_coins` BIGINT( 20 ) NOT NULL DEFAULT '0' AFTER `exponential_inflation_minershare` ;
ALTER TABLE `games` ADD `final_round` INT( 11 ) NULL DEFAULT NULL AFTER `initial_coins` ;
ALTER TABLE `games` CHANGE `game_status` `game_status` ENUM( 'unstarted', 'running', 'paused', 'completed' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'unstarted';
ALTER TABLE `games` CHANGE `giveaway_status` `giveaway_status` ENUM( 'on', 'off', 'invite_free', 'invite_pay', 'public_pay' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'off';
ALTER TABLE `games` ADD `invite_cost` DECIMAL( 10, 2 ) NOT NULL DEFAULT '0', ADD `invite_currency` INT NULL DEFAULT NULL ;
ALTER TABLE `games` ADD `featured` TINYINT(1) NOT NULL DEFAULT `0` AFTER `game_status`;
ALTER TABLE `games` CHANGE `giveaway_status` `giveaway_status` ENUM( 'on', 'invite_free', 'invite_pay', 'public_pay' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'invite_pay';
ALTER TABLE `games` ADD `coin_name` VARCHAR( 100 ) NOT NULL DEFAULT 'empirecoin' AFTER `name`, ADD `coin_name_plural` VARCHAR( 100 ) NOT NULL DEFAULT 'empirecoins' AFTER `coin_name`, ADD `coin_abbreviation` VARCHAR( 10 ) NOT NULL DEFAULT 'EMP' AFTER `coin_name_plural` ;
CREATE TABLE IF NOT EXISTS `currencies` (
  `currency_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_name` varchar(100) NOT NULL DEFAULT '',
  `abbreviation` varchar(10) NOT NULL DEFAULT '',
  `symbol` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`currency_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4;
INSERT INTO `currencies` (`currency_id`, `name`, `short_name`, `abbreviation`, `symbol`) VALUES
(1, 'US Dollar', 'dollar', 'USD', '$'),
(2, 'Bitcoin', 'bitcoin', 'BTC', '&#3647;'),
(3, 'EmpireCoin', 'empirecoin', 'EMP', 'E');
