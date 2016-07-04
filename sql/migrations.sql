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
ALTER TABLE `games` ADD `featured` TINYINT(1) NOT NULL DEFAULT '0' AFTER `game_status`;
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
CREATE TABLE IF NOT EXISTS `currency_invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_currency_id` int(11) NOT NULL,
  `settle_currency_id` int(11) NOT NULL,
  `pay_price_id` int(11) DEFAULT NULL,
  `settle_price_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `invoice_address_id` int(11) DEFAULT NULL,
  `status` enum('unpaid','unconfirmed','confirmed','settled') NOT NULL DEFAULT 'unpaid',
  `invoice_key_string` varchar(64) NOT NULL DEFAULT '',
  `pay_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `settle_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_seen` int(20) NOT NULL DEFAULT '0',
  `time_confirmed` int(20) NOT NULL DEFAULT '0',
  `expire_time` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
CREATE TABLE IF NOT EXISTS `currency_prices` (
  `price_id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_id` int(11) DEFAULT NULL,
  `reference_currency_id` int(11) DEFAULT NULL,
  `price` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `time_added` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`price_id`),
  KEY `currency_id` (`currency_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
CREATE TABLE IF NOT EXISTS `invoice_addresses` (
  `invoice_address_id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_id` int(11) DEFAULT NULL,
  `pub_key` varchar(40) NOT NULL,
  `priv_enc` varchar(300) NOT NULL,
  PRIMARY KEY (`invoice_address_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
ALTER TABLE `currency_invoices` ADD `confirmed_amount_paid` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0' AFTER `settle_amount` ,
ADD `unconfirmed_amount_paid` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0' AFTER `confirmed_amount_paid` ;
ALTER TABLE `games` ADD `start_condition` ENUM( 'fixed_time', 'players_joined' ) NOT NULL DEFAULT 'players_joined' AFTER `seconds_per_block` ;
ALTER TABLE `games` ADD `start_datetime` DATETIME NULL DEFAULT NULL AFTER `start_condition` ;
ALTER TABLE `games` ADD `start_condition_players` INT( 11 ) NULL DEFAULT NULL AFTER `start_datetime` ;
ALTER TABLE `games` CHANGE `game_status` `game_status` ENUM( 'editable', 'published', 'running', 'completed' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'editable';
ALTER TABLE `games` CHANGE `giveaway_status` `giveaway_status` ENUM( 'public_free', 'invite_free', 'invite_pay', 'public_pay' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'invite_pay';
ALTER TABLE `user_games` ADD `payment_required` TINYINT( 1 ) NOT NULL DEFAULT '0';
ALTER TABLE `currency_invoices` CHANGE `status` `status` ENUM( 'unpaid', 'unconfirmed', 'confirmed', 'settled', 'pending_refund', 'refunded' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'unpaid';
ALTER TABLE `user_games` ADD `paid_invoice_id` INT( 11 ) NULL DEFAULT NULL ;
ALTER TABLE `invitations` DROP `giveaway_transaction_id`;
ALTER TABLE `invitations` ADD `giveaway_id` INT( 11 ) NULL DEFAULT NULL AFTER `game_id` ;
