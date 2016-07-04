SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE IF NOT EXISTS `addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `option_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_mine` tinyint(1) NOT NULL DEFAULT '0',
  `address` varchar(50) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `game_id` int(11) NOT NULL DEFAULT '1',
  `bet_round_id` int(20) DEFAULT NULL,
  `bet_option_id` int(20) DEFAULT NULL,
  PRIMARY KEY (`address_id`),
  UNIQUE KEY `address` (`address`,`game_id`),
  KEY `user_id` (`user_id`),
  KEY `game_id` (`game_id`),
  KEY `is_mine` (`is_mine`),
  KEY `bet_round_id` (`bet_round_id`),
  KEY `option_id` (`option_id`),
  KEY `bet_option_id` (`bet_option_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `async_email_deliveries`
--

CREATE TABLE IF NOT EXISTS `async_email_deliveries` (
  `delivery_id` int(12) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL DEFAULT '',
  `from_email` varchar(100) NOT NULL DEFAULT '',
  `from_name` varchar(100) NOT NULL DEFAULT '',
  `subject` varchar(100) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `cc` varchar(255) NOT NULL DEFAULT '',
  `bcc` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_delivered` int(20) NOT NULL DEFAULT '0',
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `sendgrid_response` text NOT NULL,
  PRIMARY KEY (`delivery_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE IF NOT EXISTS `blocks` (
  `internal_block_id` int(11) NOT NULL AUTO_INCREMENT,
  `block_id` int(11) DEFAULT NULL,
  `block_hash` varchar(100) DEFAULT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `miner_user_id` int(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`internal_block_id`),
  UNIQUE KEY `block_id_2` (`block_id`,`game_id`),
  UNIQUE KEY `block_hash` (`block_hash`),
  KEY `miner_user_id` (`miner_user_id`),
  KEY `game_id` (`game_id`),
  KEY `block_id` (`block_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `browsers`
--

CREATE TABLE IF NOT EXISTS `browsers` (
  `browser_id` int(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE latin1_german2_ci NOT NULL,
  `display_name` varchar(255) COLLATE latin1_german2_ci NOT NULL,
  PRIMARY KEY (`browser_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `browserstrings`
--

CREATE TABLE IF NOT EXISTS `browserstrings` (
  `browserstring_id` int(30) NOT NULL AUTO_INCREMENT,
  `viewer_id` int(20) NOT NULL,
  `browser_string` varchar(255) COLLATE latin1_german2_ci NOT NULL,
  `browser_id` int(20) NOT NULL,
  `name` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `version` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `platform` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `pattern` varchar(150) COLLATE latin1_german2_ci NOT NULL,
  PRIMARY KEY (`browserstring_id`),
  KEY `v1` (`viewer_id`),
  KEY `b1` (`browser_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cached_rounds`
--

CREATE TABLE IF NOT EXISTS `cached_rounds` (
  `internal_round_id` int(20) NOT NULL AUTO_INCREMENT,
  `round_id` int(11) DEFAULT NULL,
  `payout_block_id` int(20) DEFAULT NULL,
  `payout_transaction_id` int(20) DEFAULT NULL,
  `winning_option_id` int(20) DEFAULT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `winning_score` bigint(20) NOT NULL DEFAULT '0',
  `score_sum` bigint(20) NOT NULL DEFAULT '0',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `position_1` int(8) DEFAULT NULL,
  `position_2` int(8) DEFAULT NULL,
  `position_3` int(8) DEFAULT NULL,
  `position_4` int(8) DEFAULT NULL,
  `position_5` int(8) DEFAULT NULL,
  `position_6` int(8) DEFAULT NULL,
  `position_7` int(8) DEFAULT NULL,
  `position_8` int(8) DEFAULT NULL,
  `position_9` int(8) DEFAULT NULL,
  `position_10` int(8) DEFAULT NULL,
  `position_11` int(8) DEFAULT NULL,
  `position_12` int(8) DEFAULT NULL,
  `position_13` int(8) DEFAULT NULL,
  `position_14` int(8) DEFAULT NULL,
  `position_15` int(8) DEFAULT NULL,
  `position_16` int(8) DEFAULT NULL,
  PRIMARY KEY (`internal_round_id`),
  UNIQUE KEY `round_id_2` (`round_id`,`game_id`),
  UNIQUE KEY `payout_transaction_id` (`payout_transaction_id`),
  KEY `game_id` (`game_id`),
  KEY `round_id` (`round_id`),
  KEY `payout_block_id` (`payout_block_id`),
  KEY `winning_option_id` (`winning_option_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE IF NOT EXISTS `currencies` (
  `currency_id` int(11) NOT NULL AUTO_INCREMENT,
  `oracle_url_id` INT(11) NULL DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_name` varchar(100) NOT NULL DEFAULT '',
  `abbreviation` varchar(10) NOT NULL DEFAULT '',
  `symbol` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`currency_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currency_invoices`
--

CREATE TABLE IF NOT EXISTS `currency_invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_currency_id` int(11) NOT NULL,
  `settle_currency_id` int(11) NOT NULL,
  `pay_price_id` int(11) DEFAULT NULL,
  `settle_price_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `invoice_address_id` int(11) DEFAULT NULL,
  `status` enum('unpaid','unconfirmed','confirmed','settled','pending_refund','refunded') NOT NULL DEFAULT 'unpaid',
  `invoice_key_string` varchar(64) NOT NULL DEFAULT '',
  `pay_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `settle_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `confirmed_amount_paid` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `unconfirmed_amount_paid` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_seen` int(20) NOT NULL DEFAULT '0',
  `time_confirmed` int(20) NOT NULL DEFAULT '0',
  `expire_time` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`invoice_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currency_prices`
--

CREATE TABLE IF NOT EXISTS `currency_prices` (
  `price_id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_id` int(11) DEFAULT NULL,
  `reference_currency_id` int(11) DEFAULT NULL,
  `price` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `time_added` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`price_id`),
  KEY `currency_id` (`currency_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE IF NOT EXISTS `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `creator_game_index` int(11) NOT NULL DEFAULT '0',
  `url_identifier` varchar(100) DEFAULT NULL,
  `game_type` enum('real','simulation') NOT NULL DEFAULT 'real',
  `game_status` enum('editable','published','running','completed') NOT NULL DEFAULT 'editable',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `losable_bets_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `inflation` enum('linear','exponential') NOT NULL DEFAULT 'linear',
  `exponential_inflation_minershare` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `initial_coins` bigint(20) NOT NULL DEFAULT '0',
  `final_round` int(11) DEFAULT NULL,
  `exponential_inflation_rate` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `payout_weight` enum('coin','coin_block','coin_round') NOT NULL DEFAULT 'coin_block',
  `seconds_per_block` int(11) NOT NULL DEFAULT '0',
  `start_condition` enum('fixed_time','players_joined') NOT NULL DEFAULT 'players_joined',
  `start_datetime` datetime DEFAULT NULL,
  `start_condition_players` int(11) DEFAULT NULL,
  `start_time` int(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `coin_name` varchar(100) NOT NULL DEFAULT 'empirecoin',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT 'empirecoins',
  `coin_abbreviation` varchar(10) NOT NULL DEFAULT 'EMP',
  `num_voting_options` int(10) NOT NULL DEFAULT '16',
  `max_voting_fraction` decimal(2,2) NOT NULL DEFAULT '0.25',
  `round_length` int(10) NOT NULL DEFAULT '10',
  `maturity` int(10) NOT NULL DEFAULT '8',
  `pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `pos_reward` bigint(20) NOT NULL DEFAULT '0',
  `giveaway_status` enum('public_free','invite_free','invite_pay','public_pay') NOT NULL DEFAULT 'invite_pay',
  `giveaway_amount` bigint(16) NOT NULL DEFAULT '0',
  `invite_cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `invite_currency` int(11) DEFAULT NULL,
  PRIMARY KEY (`game_id`),
  KEY `creator_id` (`creator_id`),
  KEY `game_type` (`game_type`),
  KEY `game_status` (`game_status`),
  KEY `payout_weight` (`payout_weight`),
  KEY `variation_id` (`variation_id`),
  KEY `variation_id_2` (`variation_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_giveaways`
--

CREATE TABLE IF NOT EXISTS `game_giveaways` (
  `giveaway_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `status` enum('unclaimed','claimed','redeemed') NOT NULL DEFAULT 'unclaimed',
  PRIMARY KEY (`giveaway_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_types`
--

CREATE TABLE IF NOT EXISTS `game_types` (
  `game_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_type` enum('real','simulation') NOT NULL DEFAULT 'simulation',
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `payout_weight` enum('coin','coin_block','coin_round') NOT NULL DEFAULT 'coin_round',
  `start_condition` enum('fixed_time','players_joined') NOT NULL DEFAULT 'players_joined',
  `inflation` enum('exponential','linear') NOT NULL DEFAULT 'exponential',
  `url_identifier` varchar(100) DEFAULT NULL,
  `start_condition_players` int(11) DEFAULT NULL,
  `num_voting_options` int(11) NOT NULL DEFAULT '16',
  `type_name` varchar(100) NOT NULL DEFAULT '',
  `coin_name` varchar(100) NOT NULL DEFAULT '',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT '',
  `coin_abbreviation` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`game_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_type_variations`
--

CREATE TABLE IF NOT EXISTS `game_type_variations` (
  `variation_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_type_id` int(11) DEFAULT NULL,
  `target_open_games` int(11) NOT NULL DEFAULT '0',
  `giveaway_status` enum('public_free','invite_free','public_pay','invite_pay') DEFAULT NULL,
  `giveaway_amount` bigint(20) DEFAULT NULL,
  `invite_currency` int(11) DEFAULT NULL,
  `invite_cost` decimal(16,8) DEFAULT NULL,
  `round_length` int(11) DEFAULT NULL,
  `final_round` int(11) DEFAULT NULL,
  `seconds_per_block` int(11) NOT NULL,
  `max_voting_fraction` decimal(2,2) DEFAULT NULL,
  `maturity` int(11) NOT NULL DEFAULT '0',
  `exponential_inflation_minershare` decimal(9,8) DEFAULT NULL,
  `exponential_inflation_rate` decimal(9,8) NOT NULL,
  `pow_reward` bigint(20) DEFAULT NULL,
  `pos_reward` bigint(20) DEFAULT NULL,
  `variation_name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`variation_id`),
  KEY `game_type_id` (`game_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_voting_options`
--

CREATE TABLE IF NOT EXISTS `game_voting_options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `voting_option_id` int(11) DEFAULT NULL,
  `coin_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `last_win_round` int(11) DEFAULT NULL,
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `game_id` (`game_id`,`voting_option_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `invitations`
--

CREATE TABLE IF NOT EXISTS `invitations` (
  `invitation_id` int(20) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `giveaway_id` int(11) DEFAULT NULL,
  `inviter_id` int(20) DEFAULT NULL,
  `invitation_key` varchar(100) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `sent_email_id` int(20) DEFAULT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_ip` varchar(50) NOT NULL DEFAULT '',
  `used_user_id` int(20) DEFAULT NULL,
  `used_time` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`invitation_id`),
  UNIQUE KEY `invitation_key` (`invitation_key`),
  KEY `inviter_id` (`inviter_id`),
  KEY `game_id` (`game_id`),
  KEY `used` (`used`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_addresses`
--

CREATE TABLE IF NOT EXISTS `invoice_addresses` (
  `invoice_address_id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_id` int(11) DEFAULT NULL,
  `pub_key` varchar(40) NOT NULL,
  `priv_enc` varchar(300) NOT NULL,
  PRIMARY KEY (`invoice_address_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE IF NOT EXISTS `matches` (
  `match_id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `turn_based` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','running','complete') NOT NULL DEFAULT 'pending',
  `firstplayer_position` int(11) NOT NULL DEFAULT '-1',
  `match_type_id` int(11) DEFAULT NULL,
  `num_joined` int(11) NOT NULL DEFAULT '0',
  `current_round_number` int(11) NOT NULL DEFAULT '0',
  `last_move_number` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`match_id`),
  KEY `match_type_id` (`match_type_id`),
  KEY `creator_id` (`creator_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_ios`
--

CREATE TABLE IF NOT EXISTS `match_ios` (
  `io_id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_id` int(11) DEFAULT NULL,
  `match_id` int(11) DEFAULT NULL,
  `amount` bigint(11) NOT NULL DEFAULT '0',
  `spend_status` enum('spent','unspent') NOT NULL DEFAULT 'unspent',
  `create_move_id` int(11) DEFAULT NULL,
  `spend_move_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`io_id`),
  KEY `membership_id` (`membership_id`),
  KEY `match_id` (`match_id`),
  KEY `spend_status` (`spend_status`),
  KEY `create_move_id` (`create_move_id`),
  KEY `spend_move_id` (`spend_move_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_memberships`
--

CREATE TABLE IF NOT EXISTS `match_memberships` (
  `membership_id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `player_position` int(11) NOT NULL DEFAULT '0',
  `time_joined` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`membership_id`),
  KEY `user_id` (`user_id`),
  KEY `match_id` (`match_id`),
  KEY `player_position` (`player_position`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_messages`
--

CREATE TABLE IF NOT EXISTS `match_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `hide_user_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`message_id`),
  KEY `match_id` (`match_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`),
  KEY `hide_user_id` (`hide_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_moves`
--

CREATE TABLE IF NOT EXISTS `match_moves` (
  `move_id` int(11) NOT NULL AUTO_INCREMENT,
  `membership_id` int(11) DEFAULT NULL,
  `move_type` enum('deposit','burn') NOT NULL DEFAULT 'deposit',
  `amount` bigint(11) NOT NULL DEFAULT '0',
  `time_created` int(11) NOT NULL DEFAULT '0',
  `move_number` int(11) NOT NULL DEFAULT '0',
  `round_number` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`move_id`),
  KEY `membership_id` (`membership_id`),
  KEY `round_number` (`round_number`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_rounds`
--

CREATE TABLE IF NOT EXISTS `match_rounds` (
  `match_round_id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('incomplete','won','tied') NOT NULL DEFAULT 'incomplete',
  `match_id` int(11) DEFAULT NULL,
  `round_number` int(11) NOT NULL DEFAULT '0',
  `winning_membership_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`match_round_id`),
  KEY `status` (`status`),
  KEY `round_number` (`round_number`),
  KEY `match_id` (`match_id`),
  KEY `winning_membership_id` (`winning_membership_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_types`
--

CREATE TABLE IF NOT EXISTS `match_types` (
  `match_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `num_players` int(11) NOT NULL DEFAULT '2',
  `num_rounds` int(11) NOT NULL DEFAULT '1',
  `initial_coins_per_player` bigint(11) NOT NULL DEFAULT '0',
  `max_payout_per_round` bigint(11) NOT NULL DEFAULT '0',
  `min_payout_per_round` bigint(11) NOT NULL DEFAULT '0',
  `payout_weight` enum('coin','coin_block') NOT NULL DEFAULT 'coin_block',
  PRIMARY KEY (`match_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `pageviews`
--

CREATE TABLE IF NOT EXISTS `pageviews` (
  `pageview_id` int(20) NOT NULL AUTO_INCREMENT,
  `viewer_id` int(20) NOT NULL DEFAULT '0',
  `user_id` int(20) NOT NULL DEFAULT '0',
  `browserstring_id` int(20) NOT NULL DEFAULT '0',
  `ip_id` int(20) NOT NULL DEFAULT '0',
  `cookie_id` int(20) NOT NULL DEFAULT '0',
  `time` int(20) NOT NULL DEFAULT '0',
  `pv_page_id` int(20) NOT NULL DEFAULT '0',
  `refer_url` varchar(255) NOT NULL,
  PRIMARY KEY (`pageview_id`),
  KEY `viewer_id` (`viewer_id`),
  KEY `user_id` (`user_id`),
  KEY `browserstring_id` (`browserstring_id`),
  KEY `ip_id` (`ip_id`),
  KEY `cookie_id` (`cookie_id`),
  KEY `pv_page_id` (`pv_page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `page_urls`
--

CREATE TABLE IF NOT EXISTS `page_urls` (
  `page_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`page_url_id`),
  UNIQUE KEY `url` (`url`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `redirect_urls`
--

CREATE TABLE IF NOT EXISTS `redirect_urls` (
  `redirect_url_id` int(20) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`redirect_url_id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `site_constants`
--

CREATE TABLE IF NOT EXISTS `site_constants` (
  `constant_id` int(20) NOT NULL AUTO_INCREMENT,
  `constant_name` varchar(100) NOT NULL DEFAULT '',
  `constant_value` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`constant_id`),
  UNIQUE KEY `constant_name` (`constant_name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `strategy_round_allocations`
--

CREATE TABLE IF NOT EXISTS `strategy_round_allocations` (
  `allocation_id` int(20) NOT NULL AUTO_INCREMENT,
  `strategy_id` int(20) DEFAULT NULL,
  `round_id` int(20) DEFAULT NULL,
  `option_id` int(20) DEFAULT NULL,
  `points` int(20) NOT NULL DEFAULT '0',
  `applied` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`allocation_id`),
  KEY `strategy_id` (`strategy_id`),
  KEY `round_id` (`round_id`),
  KEY `option_id` (`option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` int(20) NOT NULL AUTO_INCREMENT,
  `currency_mode` enum('beta','live') NOT NULL DEFAULT 'beta',
  `transaction_desc` enum('coinbase','giveaway','transaction','votebase','bet','betbase','') NOT NULL DEFAULT '',
  `tx_hash` varchar(64) NOT NULL DEFAULT '',
  `tx_memo` varchar(255) NOT NULL DEFAULT '',
  `amount` bigint(20) NOT NULL DEFAULT '0',
  `fee_amount` bigint(20) NOT NULL DEFAULT '0',
  `from_user_id` int(20) DEFAULT NULL,
  `to_user_id` int(20) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `block_id` int(20) DEFAULT NULL,
  `round_id` bigint(20) DEFAULT NULL,
  `vote_transaction_id` int(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  `game_id` int(11) NOT NULL DEFAULT '1',
  `bet_round_id` int(11) DEFAULT NULL,
  `ref_block_id` bigint(20) DEFAULT NULL,
  `ref_coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `ref_round_id` bigint(20) DEFAULT NULL,
  `ref_coin_rounds_destroyed` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`transaction_id`),
  KEY `user_id` (`from_user_id`),
  KEY `block_id` (`block_id`),
  KEY `vote_transaction_id` (`vote_transaction_id`),
  KEY `game_id` (`game_id`),
  KEY `tx_hash` (`tx_hash`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_ios`
--

CREATE TABLE IF NOT EXISTS `transaction_ios` (
  `io_id` int(20) NOT NULL AUTO_INCREMENT,
  `memo` varchar(100) NOT NULL DEFAULT '',
  `address_id` int(20) DEFAULT NULL,
  `game_id` int(20) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `instantly_mature` tinyint(1) NOT NULL DEFAULT '0',
  `spend_status` enum('spent','unspent','unconfirmed') NOT NULL DEFAULT 'unconfirmed',
  `out_index` int(11) DEFAULT '0',
  `create_transaction_id` int(20) DEFAULT NULL,
  `spend_transaction_id` int(20) DEFAULT NULL,
  `amount` double DEFAULT '0',
  `coin_blocks_created` bigint(20) DEFAULT NULL,
  `coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `coin_rounds_created` bigint(20) DEFAULT NULL,
  `coin_rounds_destroyed` bigint(20) DEFAULT NULL,
  `create_block_id` int(20) DEFAULT NULL,
  `spend_block_id` int(20) DEFAULT NULL,
  `create_round_id` bigint(20) DEFAULT NULL,
  `spend_round_id` bigint(20) DEFAULT NULL,
  `payout_io_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`io_id`),
  UNIQUE KEY `create_transaction_id_2` (`create_transaction_id`,`out_index`),
  KEY `address_id` (`address_id`),
  KEY `game_id` (`game_id`),
  KEY `user_id` (`user_id`),
  KEY `instantly_mature` (`instantly_mature`),
  KEY `spend_status` (`spend_status`),
  KEY `create_transaction_id` (`create_transaction_id`),
  KEY `spend_transaction_id` (`spend_transaction_id`),
  KEY `create_block_id` (`create_block_id`),
  KEY `spend_block_id` (`spend_block_id`),
  KEY `payout_io_id` (`payout_io_id`),
  KEY `option_id` (`option_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(20) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `logged_in` tinyint(4) NOT NULL DEFAULT '0',
  `account_value` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `username` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `alias_preference` enum('public','private') COLLATE latin1_german2_ci NOT NULL DEFAULT 'private',
  `alias` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `password` varchar(64) COLLATE latin1_german2_ci NOT NULL,
  `first_name` varchar(30) COLLATE latin1_german2_ci NOT NULL,
  `last_name` varchar(30) COLLATE latin1_german2_ci NOT NULL,
  `ip_address` varchar(40) COLLATE latin1_german2_ci NOT NULL,
  `time_created` int(20) NOT NULL,
  `verify_code` varchar(64) COLLATE latin1_german2_ci NOT NULL,
  `api_access_code` varchar(50) COLLATE latin1_german2_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '1',
  `notification_preference` enum('email','none') COLLATE latin1_german2_ci NOT NULL DEFAULT 'none',
  `notification_email` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `last_active` int(30) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `api_access_code` (`api_access_code`),
  KEY `password` (`password`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_games`
--

CREATE TABLE IF NOT EXISTS `user_games` (
  `user_game_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `strategy_id` int(11) DEFAULT NULL,
  `account_value` decimal(10,8) NOT NULL DEFAULT '0.00000000',
  `payment_required` tinyint(1) NOT NULL DEFAULT '0',
  `paid_invoice_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_game_id`),
  UNIQUE KEY `user_id` (`user_id`,`game_id`),
  KEY `strategy_id` (`strategy_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_messages`
--

CREATE TABLE IF NOT EXISTS `user_messages` (
  `message_id` int(20) NOT NULL AUTO_INCREMENT,
  `game_id` int(20) DEFAULT NULL,
  `from_user_id` int(20) DEFAULT NULL,
  `to_user_id` int(20) DEFAULT NULL,
  `message` text NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0',
  `send_time` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`message_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_resettokens`
--

CREATE TABLE IF NOT EXISTS `user_resettokens` (
  `token_id` int(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(20) NOT NULL DEFAULT '0',
  `token_key` varchar(64) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `token2_key` varchar(64) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `create_time` int(20) NOT NULL DEFAULT '0',
  `expire_time` int(20) NOT NULL DEFAULT '0',
  `firstclick_time` int(20) NOT NULL DEFAULT '0',
  `firstclick_ip` varchar(40) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `requester_ip` varchar(40) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `request_viewer_id` int(20) NOT NULL DEFAULT '0',
  `firstclick_viewer_id` int(20) NOT NULL DEFAULT '0',
  `completed` int(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `session_id` int(22) NOT NULL AUTO_INCREMENT,
  `login_type` enum('default','superuser') COLLATE latin1_german2_ci NOT NULL DEFAULT 'default',
  `user_id` int(20) NOT NULL DEFAULT '0',
  `session_key` varchar(32) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `login_time` int(12) NOT NULL DEFAULT '0',
  `logout_time` int(12) NOT NULL DEFAULT '0',
  `expire_time` int(12) NOT NULL DEFAULT '0',
  `ip_address` varchar(30) COLLATE latin1_german2_ci NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_strategies`
--

CREATE TABLE IF NOT EXISTS `user_strategies` (
  `strategy_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `transaction_fee` bigint(20) NOT NULL DEFAULT '100000',
  `voting_strategy` enum('manual','by_rank','by_option','by_plan','api','') NOT NULL DEFAULT 'manual',
  `aggregate_threshold` int(11) NOT NULL DEFAULT '50',
  `by_rank_ranks` varchar(100) NOT NULL DEFAULT '',
  `api_url` varchar(255) NOT NULL DEFAULT '',
  `min_votesum_pct` int(11) NOT NULL DEFAULT '0',
  `max_votesum_pct` int(11) NOT NULL DEFAULT '100',
  `min_coins_available` decimal(10,8) NOT NULL DEFAULT '0.00000000',
  `option_pct_1` int(11) NOT NULL DEFAULT '0',
  `option_pct_2` int(11) NOT NULL DEFAULT '0',
  `option_pct_3` int(11) NOT NULL DEFAULT '0',
  `option_pct_4` int(11) NOT NULL DEFAULT '0',
  `option_pct_5` int(11) NOT NULL DEFAULT '0',
  `option_pct_6` int(11) NOT NULL DEFAULT '0',
  `option_pct_7` int(11) NOT NULL DEFAULT '0',
  `option_pct_8` int(11) NOT NULL DEFAULT '0',
  `option_pct_9` int(11) NOT NULL DEFAULT '0',
  `option_pct_10` int(11) NOT NULL DEFAULT '0',
  `option_pct_11` int(11) NOT NULL DEFAULT '0',
  `option_pct_12` int(11) NOT NULL DEFAULT '0',
  `option_pct_13` int(11) NOT NULL DEFAULT '0',
  `option_pct_14` int(11) NOT NULL DEFAULT '0',
  `option_pct_15` int(11) NOT NULL DEFAULT '0',
  `option_pct_16` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`strategy_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_strategy_blocks`
--

CREATE TABLE IF NOT EXISTS `user_strategy_blocks` (
  `strategy_block_id` int(20) NOT NULL AUTO_INCREMENT,
  `strategy_id` int(20) DEFAULT NULL,
  `block_within_round` int(20) DEFAULT NULL,
  PRIMARY KEY (`strategy_block_id`),
  UNIQUE KEY `strategy_id` (`strategy_id`,`block_within_round`),
  KEY `strategy_id_2` (`strategy_id`),
  KEY `block_within_round` (`block_within_round`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewers`
--

CREATE TABLE IF NOT EXISTS `viewers` (
  `viewer_id` int(20) NOT NULL AUTO_INCREMENT,
  `account_id` int(20) NOT NULL DEFAULT '0',
  `time_created` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`viewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_connections`
--

CREATE TABLE IF NOT EXISTS `viewer_connections` (
  `connection_id` int(20) NOT NULL AUTO_INCREMENT,
  `type` enum('viewer2viewer','viewer2user') COLLATE latin1_german2_ci NOT NULL,
  `from_id` int(20) NOT NULL,
  `to_id` int(20) NOT NULL,
  PRIMARY KEY (`connection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_identifiers`
--

CREATE TABLE IF NOT EXISTS `viewer_identifiers` (
  `identifier_id` int(11) NOT NULL AUTO_INCREMENT,
  `viewer_id` int(20) NOT NULL DEFAULT '0',
  `type` enum('ip','cookie') NOT NULL,
  `identifier` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`identifier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `voting_options`
--

CREATE TABLE IF NOT EXISTS `voting_options` (
  `voting_option_id` int(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `address_character` varchar(1) NOT NULL DEFAULT '',
  PRIMARY KEY (`voting_option_id`),
  KEY `address_character` (`address_character`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `voting_option_groups`
--

CREATE TABLE IF NOT EXISTS `voting_option_groups` (
  `option_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `option_name` varchar(100) NOT NULL DEFAULT '',
  `option_name_plural` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`option_group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_buyins`
--

CREATE TABLE IF NOT EXISTS `game_buyins` (
  `buyin_id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_currency_id` int(11) DEFAULT NULL,
  `settle_currency_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `invoice_address_id` int(11) DEFAULT NULL,
  `giveaway_id` int(11) DEFAULT NULL,
  `status` enum('unpaid','unconfirmed','confirmed','settled','pending_refund','refunded') NOT NULL DEFAULT 'unpaid',
  `pay_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `settle_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `confirmed_amount_paid` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `unconfirmed_amount_paid` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_confirmed` int(20) NOT NULL DEFAULT '0',
  `expire_time` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`buyin_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `external_addresses`
--

CREATE TABLE IF NOT EXISTS `external_addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `address` varchar(100) DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`address_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oracle_urls`
--

CREATE TABLE IF NOT EXISTS `oracle_urls` (
  `oracle_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `format_id` int(11) DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`oracle_url_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

ALTER TABLE `game_voting_options` ADD `name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `voting_option_id`;
ALTER TABLE `game_voting_options` ADD `voting_character` VARCHAR(1) NOT NULL DEFAULT '' AFTER `name`;
UPDATE `game_voting_options` gvo JOIN voting_options vo ON gvo.voting_option_id=vo.voting_option_id SET gvo.name=vo.name, gvo.voting_character=vo.address_character;
ALTER TABLE `game_types` ADD `option_group_id` INT(11) NULL DEFAULT NULL AFTER `game_type_id`;
ALTER TABLE `games` ADD `option_group_id` INT(11) NULL DEFAULT NULL AFTER `variation_id`;
ALTER TABLE `voting_options` ADD `option_group_id` INT(11) NULL DEFAULT NULL AFTER `voting_option_id`;
ALTER TABLE `games` ADD `option_name` VARCHAR(100) NOT NULL DEFAULT '' , ADD `option_name_plural` VARCHAR(100) NOT NULL DEFAULT '' ;
UPDATE games g JOIN voting_option_groups og ON g.option_group_id=og.option_group_id SET g.option_name=og.option_name, g.option_name_plural=og.option_name_plural;
ALTER TABLE `game_type_variations` DROP `max_voting_fraction`;
ALTER TABLE `game_types` ADD `max_voting_fraction` DECIMAL(8,8) NULL DEFAULT NULL AFTER `num_voting_options`;
ALTER TABLE `games` ADD `type_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `name`, ADD `variation_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `type_name`;

-- --------------------------------------------------------

-- 
-- INSERTS
--

INSERT INTO `browsers` (`browser_id`, `name`, `display_name`) VALUES
(9, 'mozilla_firefox', 'Firefox'),
(10, 'unknown', 'Unknown'),
(11, 'internet_explorer', 'IE'),
(12, 'apple_safari', 'Safari'),
(13, 'google_chrome', 'Chrome'),
(14, 'opera', 'Opera');

INSERT INTO `match_types` (`match_type_id`, `name`, `num_players`, `num_rounds`, `initial_coins_per_player`, `max_payout_per_round`, `min_payout_per_round`, `payout_weight`) VALUES
(1, 'standard coin battle', 2, 10, 10000000000, 1000000000, 0, 'coin_block');

INSERT INTO `voting_options` (`voting_option_id`, `option_group_id`, `name`, `address_character`) VALUES
(1, 1, 'China', '1'),
(2, 1, 'USA', '2'),
(3, 1, 'India', '3'),
(4, 1, 'Brazil', '4'),
(5, 1, 'Indonesia', '5'),
(6, 1, 'Japan', '6'),
(7, 1, 'Russia', '7'),
(8, 1, 'Germany', '8'),
(9, 1, 'Mexico', '9'),
(10, 1, 'Nigeria', 'a'),
(11, 1, 'France', 'b'),
(12, 1, 'UK', 'c'),
(13, 1, 'Pakistan', 'd'),
(14, 1, 'Italy', 'e'),
(15, 1, 'Turkey', 'f'),
(16, 1, 'Iran', 'g'),
(17, 2, 'Bernie Sanders', '1'),
(18, 2, 'Donald Trump', '2'),
(19, 2, 'Hillary Clinton', '3'),
(21, 2, 'Joe Biden', '4'),
(20, 2, 'John Kasich', '5'),
(22, 2, 'Mitt Romney', '6'),
(23, 2, 'Paul Ryan', '7'),
(24, 2, 'Ted Cruz', '8');

INSERT INTO `currencies` (`currency_id`, `oracle_url_id`, `name`, `short_name`, `abbreviation`, `symbol`) VALUES
(1, NULL, 'US Dollar', 'dollar', 'USD', '$'),
(2, 2, 'Bitcoin', 'bitcoin', 'BTC', '&#3647;'),
(3, NULL, 'EmpireCoin', 'empirecoin', 'EMP', 'E'),
(3, 1, 'Euro', 'euro', 'EUR', '€'),
(3, 1, 'Renminbi', 'renminbi', 'CNY', '¥'),
(3, 1, 'Pound sterling', 'pound', 'GBP', '£'),
(3, 1, 'Japanese yen', 'yen', 'JPY', '¥');

INSERT INTO `site_constants` SET constant_name='reference_currency_id', constant_value=1;
INSERT INTO `currency_prices` SET currency_id=1, reference_currency_id=1, price=1;

INSERT INTO `game_types` (`game_type_id`, `option_group_id`, `game_type`, `block_timing`, `payout_weight`, `start_condition`, `inflation`, `url_identifier`, `start_condition_players`, `num_voting_options`, `max_voting_fraction`, `type_name`, `coin_name`, `coin_name_plural`, `coin_abbreviation`) VALUES
(1, 1, 'simulation', 'realistic', 'coin_round', 'players_joined', 'exponential', 'two-player-empire-battle', 2, 16, '0.40000000', '2 players, 16 empires, 40% cap', 'empirecoin', 'empirecoins', '$'),
(2, 1, 'simulation', 'realistic', 'coin_round', 'players_joined', 'exponential', 'two-player-penny-battle', 2, 16, '0.25000000', '2 players, 16 empires, 25% cap', 'dime', 'dimes', '$'),
(3, 1, 'simulation', 'realistic', 'coin_round', 'players_joined', 'exponential', 'two-player-dollar-battle', 2, 16, '0.50000000', '2 players, 16 empires, 50% cap', 'empirecoin', 'empirecoins', '$'),
(4, 2, 'simulation', 'realistic', 'coin_round', 'players_joined', 'exponential', 'presidential-election', 20, 8, '0.50000000', '20 players, 8 presidential candidates, 50% cap', 'empirecoin', 'empirecoins', 'EMP'),
(5, 1, 'simulation', 'realistic', 'coin_round', 'players_joined', 'exponential', '5-player-bitcoin-battle', 5, 16, '0.20000000', '5 players, 16 empires, 20% cap', 'bitcoin', 'bitcoins', 'BTC'),
(6, 2, 'simulation', 'realistic', 'coin_round', 'players_joined', 'exponential', '2-player-election-battle', 2, 8, '0.50000000', '2 players, 8 presidential candidates, 50% cap', 'buck', 'bucks', '$');

INSERT INTO `game_type_variations` (`variation_id`, `game_type_id`, `target_open_games`, `giveaway_status`, `giveaway_amount`, `invite_currency`, `invite_cost`, `round_length`, `final_round`, `seconds_per_block`, `maturity`, `exponential_inflation_minershare`, `exponential_inflation_rate`, `pow_reward`, `pos_reward`, `url_identifier`, `variation_name`) VALUES
(1, 1, 1, 'public_pay', 10000000000, 1, '1.00000000', 20, 10, 12, 0, '0.01000000', '0.20000000', 0, 0, 'two-player-empire-battle-100-dimes-for-$1', 'Buy 100 dimes for $1'),
(3, 3, 1, 'public_pay', 100000000000, 1, '10.00000000', 20, 10, 12, 1, '0.01000000', '0.20000000', 0, 0, 'two-player-dollar-battle-1000-empirecoins-for-$10', 'Buy 1000 empirecoins for $10'),
(4, 3, 1, 'public_free', 100000000000, 1, '0.00000000', 20, 10, 12, 0, '0.01000000', '0.20000000', 0, 0, 'two-player-dollar-battle-1000-empirecoins-free', 'Get 1000 empirecoins for free'),
(5, 4, 1, 'public_pay', 270000000000, 1, '27.00000000', 100, 100, 18, 0, '0.01000000', '0.05000000', 0, 0, 'presidential-election-2700-empirecoins-for-$27', 'Buy 2,700 coins for $27'),
(6, 4, 1, 'public_free', 270000000000, 1, '0.00000000', 100, 100, 18, 0, '0.01000000', '0.05000000', 0, 0, 'presidential-election-2700-empirecoins-free', 'Get 2,700 coins for free'),
(7, 5, 1, 'public_pay', 80000000000, 2, '0.10000000', 20, 20, 12, 0, '0.01000000', '0.10000000', 0, 0, '5-player-bitcoin-battle-800-coins-for-0.1-btc', 'Buy 800 bitcoins for 0.1 BTC'),
(8, 5, 1, 'public_free', 10000000000, 1, '0.00000000', 20, 20, 12, 0, '0.01000000', '0.10000000', 0, 0, '5-player-bitcoin-battle-800-coins-free', 'Get 800 bitcoins for free'),
(9, 6, 1, 'public_free', 500000000000, 1, '0.00000000', 30, 15, 4, 0, '0.01000000', '0.12000000', 0, 0, 'two-player-election-battle-5000-bucks-free', 'Get 5,000 bucks for free');

INSERT INTO `voting_option_groups` (`option_group_id`, `option_name`, `option_name_plural`, `description`) VALUES
(1, 'empire', 'empires', '16 biggest nations in the world'),
(2, 'candidate', 'candidates', '2016 presidential candidates');

INSERT INTO `oracle_urls` (`oracle_url_id`, `format_id`, `url`) VALUES
(1, 1, 'http://api.fixer.io/latest?base=USD'),
(2, 2, 'https://api.bitcoinaverage.com/ticker/global/all');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
