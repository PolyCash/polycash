ALTER TABLE `games` ADD `invoice_address_id` INT(11) NULL DEFAULT NULL AFTER `option_group_id`;
ALTER TABLE `games` ADD `completion_datetime` DATETIME NULL DEFAULT NULL AFTER `start_datetime`;
ALTER TABLE `games` ADD `payout_reminder_datetime` DATETIME NULL DEFAULT NULL AFTER `completion_datetime`;
ALTER TABLE `games` ADD `payout_complete` TINYINT(1) NOT NULL DEFAULT '0' AFTER `payout_reminder_datetime`;
ALTER TABLE `users` ADD `bitcoin_address_id` INT(11) NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `user_games` ADD `bitcoin_address_id` INT(11) NULL DEFAULT NULL ;
ALTER TABLE `games` ADD `payout_tx_hash` VARCHAR(255) NOT NULL DEFAULT '' AFTER `payout_complete`;
ALTER TABLE `users` ADD `authorized_games` INT(11) NOT NULL DEFAULT '0' ;
ALTER TABLE `games` ADD `buyin_policy` ENUM('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none' AFTER `payout_weight`;
ALTER TABLE `games` ADD `per_user_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0' AFTER `buyin_policy`;
ALTER TABLE `games` ADD `game_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0' AFTER `per_user_buyin_cap`;
ALTER TABLE `user_games` ADD `buyin_invoice_address_id` INT(11) NULL DEFAULT NULL ;
ALTER TABLE `game_giveaways` ADD `type` ENUM('initial_purchase','buyin') NOT NULL DEFAULT 'initial_purchase' ;
ALTER TABLE `game_giveaways` ADD `amount` BIGINT(20) NOT NULL DEFAULT '0' ;
UPDATE game_giveaways gg JOIN games ga ON gg.game_id=ga.game_id SET gg.amount=ga.giveaway_amount WHERE gg.type='initial_purchase';
ALTER TABLE `game_buyins` ADD INDEX (`pay_currency_id`);
ALTER TABLE `game_buyins` ADD INDEX (`settle_currency_id`);
ALTER TABLE `game_buyins` ADD INDEX (`user_id`);
ALTER TABLE `game_buyins` ADD INDEX (`game_id`);
ALTER TABLE `game_buyins` ADD INDEX (`invoice_address_id`);
ALTER TABLE `game_buyins` ADD INDEX (`giveaway_id`);
ALTER TABLE `game_buyins` ADD INDEX (`status`);
ALTER TABLE `games` CHANGE `invite_cost` `invite_cost` DECIMAL(16,8) NOT NULL DEFAULT '0.00';
ALTER TABLE `games` ADD `invitation_link` VARCHAR(200) NOT NULL DEFAULT '' AFTER `option_name_plural`;
CREATE TABLE IF NOT EXISTS `images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `access_key` varchar(50) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(1, 'CbGULeWBFdFHjLoE', 'jpg'),
(2, 'cAmH53sosXKTIans', 'jpg'),
(3, 'PJkb84shHd5JkZJN', 'jpg'),
(4, 'w5uNMflPjHZ2soyH', 'jpg'),
(5, 'ZGkf0Pn54OqNpHCG', 'jpg'),
(6, 'Xr38svRwT87qoHz5', 'jpg'),
(7, 'Xcqtmp7JMtPIXYKp', 'jpg'),
(8, '7ZbyYnuHAuqvZMeg', 'jpg'),
(9, 'QUetoFCnsqawYqta', 'jpg'),
(10, '8JNwcRyNX8jDCDFS', 'jpg'),
(11, '9SJzffH1p8QXSTQD', 'jpg'),
(12, 'pytNrzMUbLHm7404', 'jpg'),
(13, 'Clqh36lP7eLWXYJd', 'jpg'),
(14, 'jBxIsTQ7iVy7aaHO', 'jpg'),
(15, 'k49ZaVs16GC3UYRV', 'jpg'),
(16, 'hNe7REoWmxiWzSvP', 'jpg');
ALTER TABLE `voting_options` ADD `default_image_id` INT(11) NULL DEFAULT NULL ;
UPDATE `voting_options` SET default_image_id=voting_option_id WHERE voting_option_id<=16;
ALTER TABLE `game_voting_options` ADD `image_id` INT(11) NULL DEFAULT NULL AFTER `voting_option_id`;
UPDATE game_voting_options gvo JOIN voting_options vo ON gvo.voting_option_id=vo.voting_option_id SET gvo.image_id=vo.default_image_id;
ALTER TABLE `empirecoin`.`game_voting_options` ADD INDEX (`voting_option_id`);
ALTER TABLE `empirecoin`.`game_voting_options` ADD INDEX (`image_id`);
ALTER TABLE `user_games` ADD `show_planned_votes` TINYINT(1) NOT NULL DEFAULT '0' ;
ALTER TABLE `game_type_variations`  ADD `buyin_policy` ENUM('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none'  AFTER `giveaway_amount`,  ADD `game_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0'  AFTER `buyin_policy`,  ADD `per_user_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0'  AFTER `game_buyin_cap`;
CREATE TABLE IF NOT EXISTS `game_join_requests` (
  `join_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `request_status` enum('outstanding','complete','canceled') NOT NULL DEFAULT 'outstanding',
  `time_requested` int(20) NOT NULL,
  PRIMARY KEY (`join_request_id`),
  KEY `user_id` (`user_id`),
  KEY `variation_id` (`variation_id`),
  KEY `game_id` (`game_id`),
  KEY `request_status` (`request_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

ALTER TABLE `currencies` ADD INDEX (`oracle_url_id`);
ALTER TABLE `currencies` ADD INDEX (`abbreviation`);
ALTER TABLE `games` ADD `payout_taper_function` ENUM('constant','linear_decrease') NOT NULL DEFAULT 'constant' AFTER `payout_weight`;
ALTER TABLE `voting_options` CHANGE `address_character` `voting_character` VARCHAR(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '';
UPDATE voting_options vo JOIN game_voting_options gvo ON vo.voting_option_id=gvo.voting_option_id SET gvo.voting_character=vo.voting_character;
ALTER TABLE `games` CHANGE `creator_game_index` `creator_game_index` INT( 11 ) NULL DEFAULT NULL;
ALTER TABLE `transactions` DROP INDEX `tx_hash`, ADD UNIQUE `tx_hash` (`tx_hash`) USING BTREE;
INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(17, 'oRBJ38hs92l2Akjn', 'jpg'),
(18, 'jBXvINRIJ9hLFJyT', 'jpg'),
(19, 'Ngx8grW3QAoSKgIb', 'jpg'),
(20, 'OKuBHiGc3fGTUcfW', 'jpg'),
(21, 'qahuGE8QCWFWmmKx', 'jpg'),
(22, 'LKavHWaVm7q3xs1Q', 'jpg'),
(23, 'LTNuN3iVNskbr97D', 'jpg'),
(24, '5yOJY1mQKHxhP127', 'jpg');
UPDATE voting_options SET default_image_id=voting_option_id WHERE voting_option_id > 16 AND voting_option_id <= 24;
UPDATE game_voting_options gvo JOIN voting_options vo ON vo.voting_option_id=gvo.voting_option_id SET gvo.image_id=vo.default_image_id WHERE vo.voting_option_id > 16 AND vo.voting_option_id <= 24;
ALTER TABLE `blocks` ADD `taper_factor` DECIMAL(9,8) NOT NULL DEFAULT '1' AFTER `miner_user_id`;
ALTER TABLE `transaction_ios` ADD `votes` BIGINT(20) NOT NULL DEFAULT '0' AFTER `coin_rounds_destroyed`;
ALTER TABLE `transactions` ADD `taper_factor` DECIMAL(9,8) NOT NULL DEFAULT '1';
ALTER TABLE `game_voting_options` ADD `votes` BIGINT(20) NOT NULL DEFAULT '0' AFTER `unconfirmed_coin_round_score`, ADD `unconfirmed_votes` BIGINT(20) NOT NULL DEFAULT '0' AFTER `votes`;
INSERT INTO `voting_option_groups` (`option_group_id`, `option_name`, `option_name_plural`, `description`) VALUES
(3, 'candidate', 'candidates', 'top three 2016 presidential candidates');
INSERT INTO `voting_options` (`voting_option_id`, `option_group_id`, `name`, `voting_character`, `default_image_id`) VALUES
(27, 3, 'Hillary Clinton', '3', 19),
(26, 3, 'Donald Trump', '2', 18),
(25, 3, 'Bernie Sanders', '1', 17);
CREATE TABLE `user_strategy_options` (
  `strategy_option_id` int(11) NOT NULL,
  `strategy_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `pct_points` int(3) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
ALTER TABLE `user_strategy_options`
  ADD PRIMARY KEY (`strategy_option_id`),
  ADD UNIQUE KEY `strategy_id_2` (`strategy_id`,`option_id`),
  ADD KEY `strategy_id` (`strategy_id`);
ALTER TABLE `user_strategy_options`
  MODIFY `strategy_option_id` int(11) NOT NULL AUTO_INCREMENT;
INSERT INTO `voting_option_groups` (`option_group_id`, `option_name`, `option_name_plural`, `description`) VALUES (NULL, 'empire', 'empires', '8 biggest nations in the world');
INSERT INTO `voting_options` SET option_group_id=4, name='China', voting_character='1', default_image_id=1;
INSERT INTO `voting_options` SET option_group_id=4, name='USA', voting_character='2', default_image_id=2;
INSERT INTO `voting_options` SET option_group_id=4, name='India', voting_character='3', default_image_id=3;
INSERT INTO `voting_options` SET option_group_id=4, name='Brazil', voting_character='4', default_image_id=4;
INSERT INTO `voting_options` SET option_group_id=4, name='Indonesia', voting_character='5', default_image_id=5;
INSERT INTO `voting_options` SET option_group_id=4, name='Japan', voting_character='6', default_image_id=6;
INSERT INTO `voting_options` SET option_group_id=4, name='Russia', voting_character='7', default_image_id=7;
INSERT INTO `voting_options` SET option_group_id=4, name='Germany', voting_character='8', default_image_id=8;
CREATE TABLE `cached_round_options` (
  `round_option_id` int(11) NOT NULL,
  `internal_round_id` int(11) DEFAULT NULL,
  `round_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `score` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
ALTER TABLE `cached_round_options`
  ADD PRIMARY KEY (`round_option_id`),
  ADD KEY `round_id` (`round_id`,`game_id`);
ALTER TABLE `cached_round_options`
  MODIFY `round_option_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `cached_rounds`
  DROP `position_1`,
  DROP `position_2`,
  DROP `position_3`,
  DROP `position_4`,
  DROP `position_5`,
  DROP `position_6`,
  DROP `position_7`,
  DROP `position_8`,
  DROP `position_9`,
  DROP `position_10`,
  DROP `position_11`,
  DROP `position_12`,
  DROP `position_13`,
  DROP `position_14`,
  DROP `position_15`,
  DROP `position_16`;
ALTER TABLE `strategy_round_allocations` ADD UNIQUE (`strategy_id`, `round_id`, `option_id`);
ALTER TABLE `strategy_round_allocations` ADD INDEX (`strategy_id`, `round_id`);

