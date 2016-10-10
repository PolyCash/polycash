ALTER TABLE `currencies` DROP `has_blockchain`;
ALTER TABLE `currencies` ADD `blockchain_id` INT NULL DEFAULT NULL AFTER `oracle_url_id`;
ALTER TABLE `currencies` ADD INDEX (`blockchain_id`);
CREATE TABLE `blockchains` (
  `blockchain_id` int(11) NOT NULL,
  `blockchain_name` varchar(100) NOT NULL DEFAULT '',
  `rpc_username` varchar(100) DEFAULT NULL,
  `rpc_password` varchar(100) DEFAULT NULL,
  `rpc_port` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
ALTER TABLE `blockchains` ADD PRIMARY KEY (`blockchain_id`);
ALTER TABLE `blockchains` MODIFY `blockchain_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `blockchains` ADD `default_rpc_port` INT NULL DEFAULT NULL AFTER `rpc_port`;
ALTER TABLE `blocks` DROP `game_id`;
ALTER TABLE `blocks` ADD `blockchain_id` INT NULL DEFAULT NULL AFTER `internal_block_id`;
ALTER TABLE `addresses`
  DROP `game_id`,
  DROP `bet_round_id`,
  DROP `bet_option_id`;
ALTER TABLE `games` DROP `identifier_case_sensitive`;
ALTER TABLE `blockchains` ADD `identifier_case_sensitive` TINYINT(1) NOT NULL DEFAULT '1' AFTER `default_rpc_port`;
ALTER TABLE `games` DROP `identifier_first_char`;
ALTER TABLE `blockchains` ADD `identifier_first_char` INT NOT NULL DEFAULT '2' AFTER `identifier_case_sensitive`;
ALTER TABLE `transaction_ios`
  DROP `game_id`,
  DROP `event_id`,
  DROP `effectiveness_factor`,
  DROP `option_id`,
  DROP `colored_amount`,
  DROP `coin_rounds_created`,
  DROP `coin_rounds_destroyed`,
  DROP `create_round_id`,
  DROP `spend_round_id`,
  DROP `payout_io_id`;
ALTER TABLE `transaction_ios` DROP `votes`;
ALTER TABLE `transactions` CHANGE `game_id` `blockchain_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `transactions` DROP `round_id`;
ALTER TABLE `transactions` DROP `bet_round_id`;
ALTER TABLE `transactions` DROP `ref_round_id`;
ALTER TABLE `transactions` DROP `ref_coin_rounds_destroyed`;
ALTER TABLE `blockchains` ADD `first_required_block` INT NULL DEFAULT NULL AFTER `default_rpc_port`;
ALTER TABLE `games` ADD `blockchain_id` INT NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `transaction_ios` ADD `blockchain_id` INT NULL DEFAULT NULL AFTER `io_id`;
ALTER TABLE `blockchains` ADD `initial_pow_reward` BIGINT NOT NULL DEFAULT '0' AFTER `identifier_first_char`;
ALTER TABLE `blockchains` ADD `url_identifier` VARCHAR(100) NOT NULL DEFAULT '' AFTER `blockchain_name`;
ALTER TABLE `game_types`
  DROP `identifier_first_char`,
  DROP `identifier_case_sensitive`;
ALTER TABLE `blockchains` ADD `coin_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `url_identifier`, ADD `coin_name_plural` VARCHAR(100) NOT NULL DEFAULT '' AFTER `coin_name`;
ALTER TABLE `blocks` DROP `effectiveness_factor`;
CREATE TABLE `transaction_game_ios` (
  `game_io_id` int(11) NOT NULL,
  `io_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `colored_amount` bigint(20) NOT NULL DEFAULT '0',
  `votes` bigint(20) DEFAULT NULL,
  `effectiveness_factor` decimal(16,0) DEFAULT NULL,
  `coin_rounds_created` bigint(20) DEFAULT NULL,
  `coin_rounds_destroyed` bigint(20) DEFAULT NULL,
  `create_round_id` int(11) DEFAULT NULL,
  `spend_round_id` int(11) DEFAULT NULL,
  `payout_game_io_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
ALTER TABLE `transaction_game_ios`
  ADD PRIMARY KEY (`game_io_id`),
  ADD KEY `io_id` (`io_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `option_id` (`option_id`);
ALTER TABLE `transaction_game_ios`
  MODIFY `game_io_id` int(11) NOT NULL AUTO_INCREMENT;
CREATE TABLE `game_blocks` (
  `game_block_id` int(11) NOT NULL,
  `internal_block_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `block_id` int(11) DEFAULT NULL,
  `locally_saved` tinyint(1) NOT NULL DEFAULT '0',
  `num_transactions` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
ALTER TABLE `game_blocks`
  ADD PRIMARY KEY (`game_block_id`),
  ADD UNIQUE KEY `internal_block_id_2` (`internal_block_id`,`game_id`),
  ADD KEY `internal_block_id` (`internal_block_id`);
ALTER TABLE `game_blocks`
  MODIFY `game_block_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `games` DROP `p2p_mode`;
ALTER TABLE `blockchains` ADD `p2p_mode` ENUM('none','rpc') NOT NULL DEFAULT 'none' AFTER `url_identifier`;
ALTER TABLE `games` ADD `genesis_amount` BIGINT NULL DEFAULT NULL AFTER `genesis_tx_hash`;
ALTER TABLE `empirecoin11`.`games` ADD INDEX (`blockchain_id`);
ALTER TABLE `game_types` DROP `p2p_mode`;
ALTER TABLE `games` CHANGE `game_starting_block` `game_starting_block` INT(11) NULL DEFAULT NULL;
UPDATE game_types SET game_starting_block=NULL;
INSERT INTO `currencies` (`currency_id`, `oracle_url_id`, `blockchain_id`, `name`, `short_name`, `short_name_plural`, `abbreviation`, `symbol`) VALUES (NULL, NULL, NULL, 'Litecoin', 'litecoin', 'litecoins', 'LTC', 'L');
INSERT INTO `blockchains` (`blockchain_id`, `blockchain_name`, `url_identifier`, `p2p_mode`, `coin_name`, `coin_name_plural`, `rpc_username`, `rpc_password`, `rpc_port`, `default_rpc_port`, `first_required_block`, `identifier_case_sensitive`, `identifier_first_char`, `initial_pow_reward`) VALUES (NULL, 'Litecoin', 'litecoin', 'none', 'litecoin', 'litecoins', NULL, NULL, NULL, '9332', NULL, '1', '2', '5000000000');
UPDATE `currencies` SET `blockchain_id` = '2' WHERE `currency_id` = 8;
ALTER TABLE blocks DROP INDEX block_id_2;
ALTER TABLE `transaction_ios` DROP COLUMN `instantly_mature`;
ALTER TABLE `transaction_game_ios` ADD `instantly_mature` TINYINT(1) NOT NULL DEFAULT '0' AFTER `effectiveness_factor`;