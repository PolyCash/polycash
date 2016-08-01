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
  `game_id` int(11) NOT NULL DEFAULT '1',
  `option_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_mine` tinyint(1) NOT NULL DEFAULT '0',
  `address` varchar(50) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `bet_round_id` int(20) DEFAULT NULL,
  `bet_option_id` int(20) DEFAULT NULL,
  PRIMARY KEY (`address_id`),
  UNIQUE KEY `address` (`address`,`game_id`),
  KEY `user_id` (`user_id`),
  KEY `is_mine` (`is_mine`),
  KEY `bet_round_id` (`bet_round_id`),
  KEY `option_id` (`option_id`),
  KEY `bet_option_id` (`bet_option_id`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  `effectiveness_factor` decimal(9,8) NOT NULL DEFAULT '1.00000000',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `locally_saved` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`internal_block_id`),
  UNIQUE KEY `block_id_2` (`block_id`,`game_id`),
  UNIQUE KEY `block_hash` (`block_hash`),
  KEY `miner_user_id` (`miner_user_id`),
  KEY `block_id` (`block_id`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `browsers`
--

CREATE TABLE IF NOT EXISTS `browsers` (
  `browser_id` int(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `display_name` varchar(255) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`browser_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

--
-- Dumping data for table `browsers`
--

INSERT INTO `browsers` (`browser_id`, `name`, `display_name`) VALUES
(1, 'mozilla_firefox', 'Firefox'),
(2, 'unknown', 'Unknown'),
(3, 'internet_explorer', 'IE'),
(4, 'apple_safari', 'Safari'),
(5, 'google_chrome', 'Chrome'),
(6, 'opera', 'Opera');

-- --------------------------------------------------------

--
-- Table structure for table `browserstrings`
--

CREATE TABLE IF NOT EXISTS `browserstrings` (
  `browserstring_id` int(30) NOT NULL AUTO_INCREMENT,
  `viewer_id` int(20) DEFAULT NULL,
  `browser_string` varchar(255) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `browser_id` int(20) DEFAULT NULL,
  `name` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `version` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `platform` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `pattern` varchar(150) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`browserstring_id`),
  KEY `v1` (`viewer_id`),
  KEY `b1` (`browser_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE IF NOT EXISTS `currencies` (
  `currency_id` int(11) NOT NULL AUTO_INCREMENT,
  `oracle_url_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_name` varchar(100) NOT NULL DEFAULT '',
  `abbreviation` varchar(10) NOT NULL DEFAULT '',
  `symbol` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`currency_id`),
  KEY `oracle_url_id` (`oracle_url_id`),
  KEY `abbreviation` (`abbreviation`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`currency_id`, `oracle_url_id`, `name`, `short_name`, `abbreviation`, `symbol`) VALUES
(1, NULL, 'US Dollar', 'dollar', 'USD', '$'),
(2, 2, 'Bitcoin', 'bitcoin', 'BTC', '&#3647;'),
(3, NULL, 'EmpireCoin', 'empirecoin', 'EMP', 'E'),
(4, 1, 'Euro', 'euro', 'EUR', '€'),
(5, 1, 'Renminbi', 'renminbi', 'CNY', '¥'),
(6, 1, 'Pound sterling', 'pound', 'GBP', '£'),
(7, 1, 'Japanese yen', 'yen', 'JPY', '¥');

-- --------------------------------------------------------

--
-- Table structure for table `currency_invoices`
--

CREATE TABLE IF NOT EXISTS `currency_invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pay_currency_id` int(11) DEFAULT NULL,
  `settle_currency_id` int(11) DEFAULT NULL,
  `pay_price_id` int(11) DEFAULT NULL,
  `settle_price_id` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`invoice_id`),
  KEY `game_id` (`game_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  KEY `currency_id` (`currency_id`),
  KEY `reference_currency_id` (`reference_currency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE IF NOT EXISTS `entities` (
  `entity_id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type_id` int(11) DEFAULT NULL,
  `default_image_id` int(11) DEFAULT NULL,
  `entity_name` varchar(255) NOT NULL DEFAULT '',
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `last_name` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`entity_id`),
  KEY `entity_type_id` (`entity_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `entities`
--

INSERT INTO `entities` (`entity_id`, `entity_type_id`, `default_image_id`, `entity_name`, `first_name`, `last_name`) VALUES
(1, 1, 17, 'Bernie Sanders', 'Bernie', 'Sanders'),
(2, 1, 18, 'Donald Trump', 'Donald', 'Trump'),
(3, 1, 19, 'Hillary Clinton', 'Hillary', 'Clinton'),
(5, 2, NULL, 'Alabama', '', ''),
(6, 2, NULL, 'Alaska', '', ''),
(7, 2, NULL, 'Arizona', '', ''),
(8, 2, NULL, 'Arkansas', '', ''),
(9, 2, NULL, 'California', '', ''),
(10, 2, NULL, 'Colorado', '', ''),
(11, 2, NULL, 'Connecticut', '', ''),
(12, 2, NULL, 'Delaware', '', ''),
(13, 2, NULL, 'Florida', '', ''),
(14, 2, NULL, 'Georgia', '', ''),
(15, 2, NULL, 'Hawaii', '', ''),
(16, 2, NULL, 'Idaho', '', ''),
(17, 2, NULL, 'Illinois', '', ''),
(18, 2, NULL, 'Indiana', '', ''),
(19, 2, NULL, 'Iowa', '', ''),
(20, 2, NULL, 'Kansas', '', ''),
(21, 2, NULL, 'Kentucky', '', ''),
(22, 2, NULL, 'Louisiana', '', ''),
(23, 2, NULL, 'Maine', '', ''),
(24, 2, NULL, 'Maryland', '', ''),
(25, 2, NULL, 'Massachusetts', '', ''),
(26, 2, NULL, 'Michigan', '', ''),
(27, 2, NULL, 'Minnesota', '', ''),
(28, 2, NULL, 'Mississippi', '', ''),
(29, 2, NULL, 'Missouri', '', ''),
(30, 2, NULL, 'Montana', '', ''),
(31, 2, NULL, 'Nebraska', '', ''),
(32, 2, NULL, 'Nevada', '', ''),
(33, 2, NULL, 'New Hampshire', '', ''),
(34, 2, NULL, 'New Jersey', '', ''),
(35, 2, NULL, 'New Mexico', '', ''),
(36, 2, NULL, 'New York', '', ''),
(37, 2, NULL, 'North Carolina', '', ''),
(38, 2, NULL, 'North Dakota', '', ''),
(39, 2, NULL, 'Ohio', '', ''),
(40, 2, NULL, 'Oklahoma', '', ''),
(41, 2, NULL, 'Oregon', '', ''),
(42, 2, NULL, 'Pennsylvania', '', ''),
(43, 2, NULL, 'Rhode Island', '', ''),
(44, 2, NULL, 'South Carolina', '', ''),
(45, 2, NULL, 'South Dakota', '', ''),
(46, 2, NULL, 'Tennessee', '', ''),
(47, 2, NULL, 'Texas', '', ''),
(48, 2, NULL, 'Utah', '', ''),
(49, 2, NULL, 'Vermont', '', ''),
(50, 2, NULL, 'Virginia', '', ''),
(51, 2, NULL, 'Washington', '', ''),
(52, 2, NULL, 'West Virginia', '', ''),
(53, 2, NULL, 'Wisconsin', '', ''),
(54, 2, NULL, 'Wyoming', '', ''),
(57, 3, NULL, 'Black', '', ''),
(58, 3, 31, 'Blue', '', ''),
(59, 3, NULL, 'Green', '', ''),
(60, 3, NULL, 'Orange', '', ''),
(61, 3, NULL, 'Purple', '', ''),
(62, 3, 32, 'Red', '', ''),
(63, 3, NULL, 'White', '', ''),
(64, 3, NULL, 'Yellow', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `entity_types`
--

CREATE TABLE IF NOT EXISTS `entity_types` (
  `entity_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`entity_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `entity_types`
--

INSERT INTO `entity_types` (`entity_type_id`, `entity_name`) VALUES
(1, '2016 presidential candidates'),
(2, 'states in America'),
(3, 'empirecoin teams');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `event_type_id` int(11) NOT NULL,
  `event_starting_block` int(11) DEFAULT '0',
  `event_final_block` int(11) DEFAULT '0',
  `event_name` varchar(255) NOT NULL DEFAULT '',
  `option_name` varchar(255) NOT NULL DEFAULT '',
  `option_name_plural` varchar(255) NOT NULL DEFAULT '',
  `start_datetime` datetime DEFAULT NULL,
  `completion_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `game_id` (`game_id`),
  KEY `event_type_id` (`event_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `event_outcomes`
--

CREATE TABLE IF NOT EXISTS `event_outcomes` (
  `outcome_id` int(20) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL DEFAULT '1',
  `round_id` int(11) DEFAULT NULL,
  `sum_votes` bigint(20) NOT NULL DEFAULT '0',
  `winning_option_id` int(20) DEFAULT NULL,
  `winning_votes` bigint(20) NOT NULL DEFAULT '0',
  `derived_winning_option_id` int(11) DEFAULT NULL,
  `derived_winning_votes` bigint(20) NOT NULL DEFAULT '0',
  `payout_block_id` int(20) DEFAULT NULL,
  `payout_transaction_id` int(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`outcome_id`),
  UNIQUE KEY `round_id_2` (`round_id`,`event_id`),
  UNIQUE KEY `payout_transaction_id` (`payout_transaction_id`),
  KEY `event_id` (`event_id`),
  KEY `round_id` (`round_id`),
  KEY `payout_block_id` (`payout_block_id`),
  KEY `winning_option_id` (`winning_option_id`),
  KEY `derived_winning_option_id` (`derived_winning_option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `event_outcome_options`
--

CREATE TABLE IF NOT EXISTS `event_outcome_options` (
  `round_option_id` int(11) NOT NULL AUTO_INCREMENT,
  `outcome_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `round_id` int(11) DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `coin_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `votes` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`round_option_id`),
  KEY `round_id` (`round_id`,`event_id`),
  KEY `option_id` (`option_id`),
  KEY `outcome_id` (`outcome_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

CREATE TABLE IF NOT EXISTS `event_types` (
  `event_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `option_group_id` int(11) DEFAULT NULL,
  `url_identifier` varchar(100) DEFAULT NULL,
  `vote_effectiveness_function` enum('constant','linear_decrease') NOT NULL DEFAULT 'constant',
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_description` text NOT NULL,
  `num_voting_options` int(10) NOT NULL DEFAULT '0',
  `max_voting_fraction` decimal(2,2) NOT NULL DEFAULT '0.25',
  PRIMARY KEY (`event_type_id`),
  KEY `option_group_id` (`option_group_id`),
  KEY `url_identifier` (`url_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE IF NOT EXISTS `games` (
  `game_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_type_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `invoice_address_id` int(11) DEFAULT NULL,
  `max_voting_chars` varchar(100) NOT NULL DEFAULT '',
  `creator_game_index` int(11) DEFAULT NULL,
  `url_identifier` varchar(100) DEFAULT NULL,
  `game_type` enum('real','simulation') NOT NULL DEFAULT 'real',
  `game_status` enum('editable','published','running','completed') NOT NULL DEFAULT 'editable',
  `rpc_port` int(11) DEFAULT NULL,
  `rpc_username` varchar(255) DEFAULT NULL,
  `rpc_password` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `featured_score` float NOT NULL DEFAULT '0',
  `losable_bets_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `inflation` enum('linear','exponential','fixed_exponential') NOT NULL DEFAULT 'linear',
  `exponential_inflation_minershare` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `initial_coins` bigint(20) NOT NULL DEFAULT '0',
  `game_starting_block` int(11) NOT NULL DEFAULT '0',
  `final_round` int(11) DEFAULT NULL,
  `exponential_inflation_rate` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `payout_weight` enum('coin','coin_block','coin_round') NOT NULL DEFAULT 'coin_block',
  `buyin_policy` enum('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none',
  `per_user_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `game_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `seconds_per_block` int(11) NOT NULL DEFAULT '0',
  `start_condition` enum('fixed_time','players_joined') NOT NULL DEFAULT 'players_joined',
  `start_datetime` datetime DEFAULT NULL,
  `completion_datetime` datetime DEFAULT NULL,
  `payout_reminder_datetime` datetime DEFAULT NULL,
  `payout_complete` tinyint(1) NOT NULL DEFAULT '0',
  `payout_tx_hash` varchar(255) NOT NULL DEFAULT '',
  `start_condition_players` int(11) DEFAULT NULL,
  `start_time` int(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_description` text NOT NULL,
  `coin_name` varchar(100) NOT NULL DEFAULT 'coin',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT 'coins',
  `coin_abbreviation` varchar(10) NOT NULL DEFAULT '',
  `round_length` int(10) NOT NULL DEFAULT '10',
  `maturity` int(10) NOT NULL DEFAULT '0',
  `pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `pos_reward` bigint(20) NOT NULL DEFAULT '0',
  `giveaway_status` enum('public_free','invite_free','invite_pay','public_pay') NOT NULL DEFAULT 'invite_pay',
  `giveaway_amount` bigint(16) NOT NULL DEFAULT '0',
  `public_unclaimed_invitations` tinyint(1) NOT NULL DEFAULT '0',
  `invite_cost` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `invite_currency` int(11) DEFAULT NULL,
  `invitation_link` varchar(200) NOT NULL DEFAULT '',
  `always_generate_coins` tinyint(1) NOT NULL DEFAULT '0',
  `min_unallocated_addresses` int(11) NOT NULL DEFAULT '2',
  `sync_coind_by_cron` tinyint(1) NOT NULL DEFAULT '0',
  `coins_in_existence` bigint(20) NOT NULL DEFAULT '0',
  `coins_in_existence_block` int(11) DEFAULT NULL,
  `send_round_notifications` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`game_id`),
  KEY `creator_id` (`creator_id`),
  KEY `game_type` (`game_type`),
  KEY `game_status` (`game_status`),
  KEY `payout_weight` (`payout_weight`),
  KEY `game_type_id` (`game_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_buyins`
--

CREATE TABLE IF NOT EXISTS `game_buyins` (
  `buyin_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pay_currency_id` int(11) DEFAULT NULL,
  `settle_currency_id` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`buyin_id`),
  KEY `pay_currency_id` (`pay_currency_id`),
  KEY `settle_currency_id` (`settle_currency_id`),
  KEY `user_id` (`user_id`),
  KEY `invoice_address_id` (`invoice_address_id`),
  KEY `giveaway_id` (`giveaway_id`),
  KEY `status` (`status`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_giveaways`
--

CREATE TABLE IF NOT EXISTS `game_giveaways` (
  `giveaway_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `status` enum('unclaimed','claimed','redeemed') NOT NULL DEFAULT 'unclaimed',
  `type` enum('initial_purchase','buyin') NOT NULL DEFAULT 'initial_purchase',
  `amount` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`giveaway_id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `game_id` (`game_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_invitations`
--

CREATE TABLE IF NOT EXISTS `game_invitations` (
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
  KEY `used` (`used`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_join_requests`
--

CREATE TABLE IF NOT EXISTS `game_join_requests` (
  `join_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `request_status` enum('outstanding','complete','canceled') NOT NULL DEFAULT 'outstanding',
  `time_requested` int(20) NOT NULL,
  PRIMARY KEY (`join_request_id`),
  KEY `user_id` (`user_id`),
  KEY `request_status` (`request_status`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_types`
--

CREATE TABLE IF NOT EXISTS `game_types` (
  `game_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_rule` enum('entity_type_option_group') DEFAULT NULL,
  `event_entity_type_id` int(11) DEFAULT NULL,
  `option_group_id` int(11) DEFAULT NULL,
  `events_per_round` int(11) NOT NULL DEFAULT '0',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `url_identifier` varchar(255) NOT NULL DEFAULT '',
  `target_open_games` int(11) NOT NULL DEFAULT '0',
  `currency_id` int(11) DEFAULT NULL,
  `game_type` enum('real','simulation') NOT NULL DEFAULT 'real',
  `rpc_port` int(11) DEFAULT NULL,
  `rpc_username` varchar(255) DEFAULT NULL,
  `rpc_password` varchar(255) DEFAULT NULL,
  `losable_bets_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `inflation` enum('linear','exponential','fixed_exponential') NOT NULL DEFAULT 'linear',
  `exponential_inflation_minershare` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `game_starting_block` int(11) DEFAULT NULL,
  `final_round` int(11) DEFAULT NULL,
  `exponential_inflation_rate` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `payout_weight` enum('coin','coin_block','coin_round') NOT NULL DEFAULT 'coin_block',
  `buyin_policy` enum('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none',
  `per_user_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `game_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `seconds_per_block` int(11) NOT NULL DEFAULT '0',
  `start_condition` enum('fixed_time','players_joined') NOT NULL DEFAULT 'players_joined',
  `start_condition_players` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_description` text NOT NULL,
  `coin_name` varchar(100) NOT NULL DEFAULT 'coin',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT 'coins',
  `coin_abbreviation` varchar(10) NOT NULL DEFAULT '',
  `round_length` int(10) NOT NULL DEFAULT '10',
  `maturity` int(10) NOT NULL DEFAULT '8',
  `pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `pos_reward` bigint(20) NOT NULL DEFAULT '0',
  `giveaway_status` enum('public_free','invite_free','invite_pay','public_pay') NOT NULL DEFAULT 'invite_pay',
  `giveaway_amount` bigint(16) NOT NULL DEFAULT '0',
  `public_unclaimed_invitations` tinyint(1) NOT NULL DEFAULT '0',
  `invite_cost` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `invite_currency` int(11) DEFAULT NULL,
  `invitation_link` varchar(200) NOT NULL DEFAULT '',
  `always_generate_coins` tinyint(1) NOT NULL DEFAULT '0',
  `min_unallocated_addresses` int(11) NOT NULL DEFAULT '2',
  `sync_coind_by_cron` tinyint(1) NOT NULL DEFAULT '0',
  `send_round_notifications` tinyint(1) DEFAULT '1',
  `default_vote_effectiveness_function` enum('constant','linear_decrease') DEFAULT NULL,
  `default_max_voting_fraction` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  PRIMARY KEY (`game_type_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `game_types`
--

INSERT INTO `game_types` (`game_type_id`, `event_rule`, `event_entity_type_id`, `option_group_id`, `events_per_round`, `featured`, `url_identifier`, `target_open_games`, `currency_id`, `game_type`, `rpc_port`, `rpc_username`, `rpc_password`, `losable_bets_enabled`, `block_timing`, `inflation`, `exponential_inflation_minershare`, `game_starting_block`, `final_round`, `exponential_inflation_rate`, `payout_weight`, `buyin_policy`, `per_user_buyin_cap`, `game_buyin_cap`, `seconds_per_block`, `start_condition`, `start_condition_players`, `name`, `short_description`, `coin_name`, `coin_name_plural`, `coin_abbreviation`, `round_length`, `maturity`, `pow_reward`, `pos_reward`, `giveaway_status`, `giveaway_amount`, `public_unclaimed_invitations`, `invite_cost`, `invite_currency`, `invitation_link`, `always_generate_coins`, `min_unallocated_addresses`, `sync_coind_by_cron`, `send_round_notifications`, `default_vote_effectiveness_function`, `default_max_voting_fraction`) VALUES
(1, 'entity_type_option_group', 2, 1, 2, 1, 'mock-election-2016', 1, NULL, 'simulation', NULL, NULL, NULL, 0, 'realistic', 'exponential', '0.00500000', 0, 25, '0.10000000', 'coin_round', 'none', '0.00000000', '0.00000000', 30, 'players_joined', 2, 'Mock Election 2016', '', 'votecoin', 'votecoins', 'VTC', 20, 0, 0, 0, 'public_free', 50000000000, 0, '0.00000000', 1, '', 0, 2, 0, 1, 'linear_decrease', '0.70000000');

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE IF NOT EXISTS `images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `access_key` varchar(50) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`image_id`),
  KEY `access_key` (`access_key`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `images`
--

INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(1, '', 'jpg'),
(2, '', 'jpg'),
(3, '', 'jpg'),
(4, '', 'jpg'),
(5, '', 'jpg'),
(6, '', 'jpg'),
(7, '', 'jpg'),
(8, '', 'jpg'),
(9, '', 'jpg'),
(10, '', 'jpg'),
(11, '', 'jpg'),
(12, '', 'jpg'),
(13, '', 'jpg'),
(14, '', 'jpg'),
(15, '', 'jpg'),
(16, '', 'jpg'),
(17, '', 'jpg'),
(18, '', 'jpg'),
(19, '', 'jpg'),
(20, '', 'jpg'),
(21, '', 'jpg'),
(22, '', 'jpg'),
(23, '', 'jpg'),
(24, '', 'jpg'),
(25, '', 'png'),
(26, '', 'png'),
(27, '', 'png'),
(28, '', 'png'),
(29, '', 'png'),
(30, '', 'jpg'),
(31, '', 'png'),
(32, '', 'png');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_addresses`
--

CREATE TABLE IF NOT EXISTS `invoice_addresses` (
  `invoice_address_id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_id` int(11) DEFAULT NULL,
  `pub_key` varchar(40) NOT NULL DEFAULT '',
  `priv_enc` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`invoice_address_id`),
  KEY `currency_id` (`currency_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `log_messages`
--

CREATE TABLE IF NOT EXISTS `log_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `message` varchar(255) NOT NULL DEFAULT '',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL AUTO_INCREMENT,
  `email_address` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`subscriber_id`),
  UNIQUE KEY `email_address` (`email_address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE IF NOT EXISTS `options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `membership_id` int(11) DEFAULT NULL,
  `image_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `voting_character` varchar(20) NOT NULL DEFAULT '',
  `coin_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `votes` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_votes` bigint(20) NOT NULL DEFAULT '0',
  `last_win_round` int(11) DEFAULT NULL,
  PRIMARY KEY (`option_id`),
  KEY `voting_option_id` (`entity_id`),
  KEY `image_id` (`image_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `option_groups`
--

CREATE TABLE IF NOT EXISTS `option_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `option_name` varchar(100) NOT NULL DEFAULT '',
  `option_name_plural` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `option_groups`
--

INSERT INTO `option_groups` (`group_id`, `option_name`, `option_name_plural`, `description`) VALUES
(1, 'candidate', 'candidates', 'top two 2016 presidential candidates'),
(2, 'candidate', 'candidates', 'top three 2016 presidential candidates'),
(3, 'team', 'teams', 'Red & Blue teams');

-- --------------------------------------------------------

--
-- Table structure for table `option_group_memberships`
--

CREATE TABLE IF NOT EXISTS `option_group_memberships` (
  `membership_id` int(20) NOT NULL AUTO_INCREMENT,
  `option_group_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`membership_id`),
  KEY `option_group_id` (`option_group_id`),
  KEY `entity_id` (`entity_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `option_group_memberships`
--

INSERT INTO `option_group_memberships` (`membership_id`, `option_group_id`, `entity_id`) VALUES
(1, 2, 1),
(2, 2, 2),
(3, 2, 3),
(4, 3, 58),
(5, 3, 61),
(6, 1, 2),
(7, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `oracle_urls`
--

CREATE TABLE IF NOT EXISTS `oracle_urls` (
  `oracle_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `format_id` int(11) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`oracle_url_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

--
-- Dumping data for table `oracle_urls`
--

INSERT INTO `oracle_urls` (`oracle_url_id`, `format_id`, `url`) VALUES
(1, 1, 'http://api.fixer.io/latest?base=USD'),
(2, 2, 'https://api.bitcoinaverage.com/ticker/global/all');

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
  `refer_url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`pageview_id`),
  KEY `viewer_id` (`viewer_id`),
  KEY `user_id` (`user_id`),
  KEY `browserstring_id` (`browserstring_id`),
  KEY `ip_id` (`ip_id`),
  KEY `cookie_id` (`cookie_id`),
  KEY `pv_page_id` (`pv_page_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `page_urls`
--

CREATE TABLE IF NOT EXISTS `page_urls` (
  `page_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`page_url_id`),
  UNIQUE KEY `url` (`url`) USING BTREE
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

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
  UNIQUE KEY `strategy_id_2` (`strategy_id`,`round_id`,`option_id`),
  KEY `strategy_id` (`strategy_id`),
  KEY `round_id` (`round_id`),
  KEY `option_id` (`option_id`),
  KEY `strategy_id_3` (`strategy_id`,`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` int(20) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `block_id` int(20) DEFAULT NULL,
  `round_id` bigint(20) DEFAULT NULL,
  `transaction_desc` enum('coinbase','giveaway','transaction','votebase','bet','betbase','') NOT NULL DEFAULT '',
  `tx_hash` varchar(64) NOT NULL DEFAULT '',
  `tx_memo` varchar(255) NOT NULL DEFAULT '',
  `amount` bigint(20) NOT NULL DEFAULT '0',
  `fee_amount` bigint(20) NOT NULL DEFAULT '0',
  `from_user_id` int(20) DEFAULT NULL,
  `to_user_id` int(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  `bet_round_id` int(11) DEFAULT NULL,
  `ref_block_id` bigint(20) DEFAULT NULL,
  `ref_coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `ref_round_id` bigint(20) DEFAULT NULL,
  `ref_coin_rounds_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `has_all_inputs` tinyint(1) NOT NULL DEFAULT '0',
  `has_all_outputs` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `tx_hash` (`tx_hash`) USING BTREE,
  KEY `user_id` (`from_user_id`),
  KEY `block_id` (`block_id`),
  KEY `event_id` (`game_id`),
  KEY `transaction_desc` (`transaction_desc`,`game_id`),
  KEY `round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_ios`
--

CREATE TABLE IF NOT EXISTS `transaction_ios` (
  `io_id` int(20) NOT NULL AUTO_INCREMENT,
  `game_id` int(20) DEFAULT NULL,
  `address_id` int(20) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `effectiveness_factor` decimal(9,8) NOT NULL DEFAULT '1.00000000',
  `option_id` int(11) DEFAULT NULL,
  `instantly_mature` tinyint(1) NOT NULL DEFAULT '0',
  `spend_status` enum('spent','unspent','unconfirmed') NOT NULL DEFAULT 'unconfirmed',
  `out_index` int(11) DEFAULT '0',
  `create_transaction_id` int(20) DEFAULT NULL,
  `spend_transaction_id` int(20) DEFAULT NULL,
  `amount` double DEFAULT '0',
  `coin_blocks_created` bigint(20) NOT NULL DEFAULT '0',
  `coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `coin_rounds_created` bigint(20) NOT NULL DEFAULT '0',
  `coin_rounds_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `votes` bigint(20) NOT NULL DEFAULT '0',
  `create_block_id` int(20) DEFAULT NULL,
  `spend_block_id` int(20) DEFAULT NULL,
  `spend_count` int(11) DEFAULT '0',
  `spend_transaction_ids` varchar(100) NOT NULL DEFAULT '',
  `create_round_id` bigint(20) DEFAULT NULL,
  `spend_round_id` bigint(20) DEFAULT NULL,
  `payout_io_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`io_id`),
  UNIQUE KEY `create_transaction_id_2` (`create_transaction_id`,`out_index`),
  KEY `address_id` (`address_id`),
  KEY `event_id` (`game_id`),
  KEY `user_id` (`user_id`),
  KEY `instantly_mature` (`instantly_mature`),
  KEY `spend_status` (`spend_status`),
  KEY `create_transaction_id` (`create_transaction_id`),
  KEY `spend_transaction_id` (`spend_transaction_id`),
  KEY `create_block_id` (`create_block_id`),
  KEY `spend_block_id` (`spend_block_id`),
  KEY `payout_io_id` (`payout_io_id`),
  KEY `option_id` (`option_id`),
  KEY `create_round_id` (`create_round_id`),
  KEY `spend_round_id` (`spend_round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(20) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `bitcoin_address_id` int(11) DEFAULT NULL,
  `logged_in` tinyint(4) NOT NULL DEFAULT '0',
  `login_method` enum('password','email') COLLATE latin1_german2_ci NOT NULL DEFAULT 'password',
  `account_value` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `username` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `alias_preference` enum('public','private') COLLATE latin1_german2_ci NOT NULL DEFAULT 'private',
  `alias` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `password` varchar(64) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `first_name` varchar(30) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `last_name` varchar(30) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `ip_address` varchar(40) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `time_created` int(20) DEFAULT NULL,
  `verify_code` varchar(64) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `api_access_code` varchar(50) COLLATE latin1_german2_ci DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT '1',
  `notification_email` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `last_active` int(30) NOT NULL DEFAULT '0',
  `authorized_games` int(11) NOT NULL DEFAULT '0',
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
  `account_value` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `payment_required` tinyint(1) NOT NULL DEFAULT '0',
  `paid_invoice_id` int(11) DEFAULT NULL,
  `bitcoin_address_id` int(11) DEFAULT NULL,
  `buyin_invoice_address_id` int(11) DEFAULT NULL,
  `show_planned_votes` tinyint(1) NOT NULL DEFAULT '0',
  `notification_preference` enum('email','none') NOT NULL DEFAULT 'none',
  PRIMARY KEY (`user_game_id`),
  UNIQUE KEY `user_id` (`user_id`,`game_id`),
  KEY `strategy_id` (`strategy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  `ip_address` varchar(30) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

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
  PRIMARY KEY (`strategy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_strategy_options`
--

CREATE TABLE IF NOT EXISTS `user_strategy_options` (
  `strategy_option_id` int(11) NOT NULL AUTO_INCREMENT,
  `strategy_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `pct_points` int(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`strategy_option_id`),
  UNIQUE KEY `strategy_id_2` (`strategy_id`,`option_id`),
  KEY `strategy_id` (`strategy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewers`
--

CREATE TABLE IF NOT EXISTS `viewers` (
  `viewer_id` int(20) NOT NULL AUTO_INCREMENT,
  `account_id` int(20) NOT NULL DEFAULT '0',
  `time_created` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`viewer_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_connections`
--

CREATE TABLE IF NOT EXISTS `viewer_connections` (
  `connection_id` int(20) NOT NULL AUTO_INCREMENT,
  `type` enum('viewer2viewer','viewer2user') COLLATE latin1_german2_ci NOT NULL DEFAULT 'viewer2user',
  `from_id` int(20) NOT NULL DEFAULT '0',
  `to_id` int(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`connection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_identifiers`
--

CREATE TABLE IF NOT EXISTS `viewer_identifiers` (
  `identifier_id` int(11) NOT NULL AUTO_INCREMENT,
  `viewer_id` int(20) NOT NULL DEFAULT '0',
  `type` enum('ip','cookie') NOT NULL DEFAULT 'ip',
  `identifier` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`identifier_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
