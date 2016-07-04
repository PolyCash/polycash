SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `nation_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_mine` tinyint(1) NOT NULL DEFAULT '0',
  `address` varchar(50) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `game_id` int(11) NOT NULL DEFAULT '1',
  `bet_round_id` int(20) DEFAULT NULL,
  `bet_nation_id` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `async_email_deliveries`
--

CREATE TABLE `async_email_deliveries` (
  `delivery_id` int(12) NOT NULL,
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
  `sendgrid_response` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `internal_block_id` int(11) NOT NULL,
  `block_id` int(11) DEFAULT NULL,
  `block_hash` varchar(100) DEFAULT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `miner_user_id` int(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `browsers`
--

CREATE TABLE `browsers` (
  `browser_id` int(20) NOT NULL,
  `name` varchar(255) COLLATE latin1_german2_ci NOT NULL,
  `display_name` varchar(255) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

--
-- Dumping data for table `browsers`
--

INSERT INTO `browsers` (`browser_id`, `name`, `display_name`) VALUES
(9, 'mozilla_firefox', 'Firefox'),
(10, 'unknown', 'Unknown'),
(11, 'internet_explorer', 'IE'),
(12, 'apple_safari', 'Safari'),
(13, 'google_chrome', 'Chrome'),
(14, 'opera', 'Opera');

-- --------------------------------------------------------

--
-- Table structure for table `browserstrings`
--

CREATE TABLE `browserstrings` (
  `browserstring_id` int(30) NOT NULL,
  `viewer_id` int(20) NOT NULL,
  `browser_string` varchar(255) COLLATE latin1_german2_ci NOT NULL,
  `browser_id` int(20) NOT NULL,
  `name` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `version` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `platform` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `pattern` varchar(150) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cached_rounds`
--

CREATE TABLE `cached_rounds` (
  `internal_round_id` int(20) NOT NULL,
  `round_id` int(11) DEFAULT NULL,
  `payout_block_id` int(20) DEFAULT NULL,
  `winning_nation_id` int(20) DEFAULT NULL,
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
  `position_16` int(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `creator_game_index` int(11) NOT NULL DEFAULT '0',
  `game_type` enum('real','simulation') NOT NULL DEFAULT 'real',
  `game_status` enum('unstarted','running','paused') NOT NULL DEFAULT 'unstarted',
  `losable_bets_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `payout_weight` enum('coin','coin_block') NOT NULL DEFAULT 'coin_block',
  `seconds_per_block` int(11) NOT NULL DEFAULT '0',
  `name` varchar(100) NOT NULL DEFAULT '',
  `num_voting_options` int(10) NOT NULL DEFAULT '16',
  `max_voting_fraction` decimal(2,2) NOT NULL DEFAULT '0.25',
  `round_length` int(10) NOT NULL DEFAULT '10',
  `maturity` int(10) NOT NULL DEFAULT '8',
  `pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `pos_reward` bigint(20) NOT NULL DEFAULT '0',
  `giveaway_status` enum('on','off','invite_only') NOT NULL DEFAULT 'off',
  `giveaway_amount` bigint(16) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_nations`
--

CREATE TABLE `game_nations` (
  `game_nation_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `nation_id` int(11) DEFAULT NULL,
  `coin_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `last_win_round` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `invitations`
--

CREATE TABLE `invitations` (
  `invitation_id` int(20) NOT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `inviter_id` int(20) DEFAULT NULL,
  `invitation_key` varchar(100) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_ip` varchar(50) NOT NULL DEFAULT '',
  `used_user_id` int(20) DEFAULT NULL,
  `used_time` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `match_id` int(11) NOT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `turn_based` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','running','complete') NOT NULL DEFAULT 'pending',
  `firstplayer_position` int(11) NOT NULL DEFAULT '-1',
  `match_type_id` int(11) DEFAULT NULL,
  `num_joined` int(11) NOT NULL DEFAULT '0',
  `current_round_number` int(11) NOT NULL DEFAULT '0',
  `last_move_number` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_IOs`
--

CREATE TABLE `match_IOs` (
  `io_id` int(11) NOT NULL,
  `membership_id` int(11) DEFAULT NULL,
  `match_id` int(11) DEFAULT NULL,
  `amount` bigint(11) NOT NULL DEFAULT '0',
  `spend_status` enum('spent','unspent') NOT NULL DEFAULT 'unspent',
  `create_move_id` int(11) DEFAULT NULL,
  `spend_move_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_memberships`
--

CREATE TABLE `match_memberships` (
  `membership_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `player_position` int(11) NOT NULL DEFAULT '0',
  `time_joined` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_messages`
--

CREATE TABLE `match_messages` (
  `message_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `hide_user_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_moves`
--

CREATE TABLE `match_moves` (
  `move_id` int(11) NOT NULL,
  `membership_id` int(11) DEFAULT NULL,
  `move_type` enum('deposit','burn') NOT NULL DEFAULT 'deposit',
  `amount` bigint(11) NOT NULL DEFAULT '0',
  `time_created` int(11) NOT NULL DEFAULT '0',
  `move_number` int(11) NOT NULL DEFAULT '0',
  `round_number` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_rounds`
--

CREATE TABLE `match_rounds` (
  `match_round_id` int(11) NOT NULL,
  `status` enum('incomplete','won','tied') NOT NULL DEFAULT 'incomplete',
  `match_id` int(11) DEFAULT NULL,
  `round_number` int(11) NOT NULL DEFAULT '0',
  `winning_membership_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `match_types`
--

CREATE TABLE `match_types` (
  `match_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `num_players` int(11) NOT NULL DEFAULT '2',
  `num_rounds` int(11) NOT NULL DEFAULT '1',
  `initial_coins_per_player` bigint(11) NOT NULL DEFAULT '0',
  `max_payout_per_round` bigint(11) NOT NULL DEFAULT '0',
  `min_payout_per_round` bigint(11) NOT NULL DEFAULT '0',
  `payout_weight` enum('coin','coin_block') NOT NULL DEFAULT 'coin_block'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `match_types`
--

INSERT INTO `match_types` (`match_type_id`, `name`, `num_players`, `num_rounds`, `initial_coins_per_player`, `max_payout_per_round`, `min_payout_per_round`, `payout_weight`) VALUES
(1, 'standard coin battle', 2, 10, 10000000000, 1000000000, 0, 'coin_block');

-- --------------------------------------------------------

--
-- Table structure for table `nations`
--

CREATE TABLE `nations` (
  `nation_id` int(20) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `address_character` varchar(1) NOT NULL DEFAULT '',
  `relevant_wins` int(20) NOT NULL DEFAULT '1',
  `vote_id` int(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `nations`
--

INSERT INTO `nations` (`nation_id`, `name`, `address_character`, `relevant_wins`, `vote_id`) VALUES
(1, 'China', '1', 1, 0),
(2, 'USA', '2', 1, 1),
(3, 'India', '3', 1, 2),
(4, 'Brazil', '4', 1, 3),
(5, 'Indonesia', '5', 1, 4),
(6, 'Japan', '6', 1, 5),
(7, 'Russia', '7', 1, 6),
(8, 'Germany', '8', 1, 7),
(9, 'Mexico', '9', 1, 8),
(10, 'Nigeria', 'a', 1, 9),
(11, 'France', 'b', 1, 10),
(12, 'UK', 'c', 1, 11),
(13, 'Pakistan', 'd', 1, 12),
(14, 'Italy', 'e', 1, 13),
(15, 'Turkey', 'f', 1, 14),
(16, 'Iran', 'g', 1, 15);

-- --------------------------------------------------------

--
-- Table structure for table `pageviews`
--

CREATE TABLE `pageviews` (
  `pageview_id` int(20) NOT NULL,
  `viewer_id` int(20) NOT NULL DEFAULT '0',
  `user_id` int(20) NOT NULL DEFAULT '0',
  `browserstring_id` int(20) NOT NULL DEFAULT '0',
  `ip_id` int(20) NOT NULL DEFAULT '0',
  `cookie_id` int(20) NOT NULL DEFAULT '0',
  `time` int(20) NOT NULL DEFAULT '0',
  `pv_page_id` int(20) NOT NULL DEFAULT '0',
  `refer_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `page_urls`
--

CREATE TABLE `page_urls` (
  `page_url_id` int(11) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `redirect_urls`
--

CREATE TABLE `redirect_urls` (
  `redirect_url_id` int(20) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `site_constants`
--

CREATE TABLE `site_constants` (
  `constant_id` int(20) NOT NULL,
  `constant_name` varchar(100) NOT NULL DEFAULT '',
  `constant_value` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_IOs`
--

CREATE TABLE `transaction_IOs` (
  `io_id` int(20) NOT NULL,
  `memo` varchar(100) NOT NULL DEFAULT '',
  `address_id` int(20) DEFAULT NULL,
  `game_id` int(20) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `nation_id` int(11) DEFAULT NULL,
  `instantly_mature` tinyint(1) NOT NULL DEFAULT '0',
  `spend_status` enum('spent','unspent','unconfirmed') NOT NULL DEFAULT 'unconfirmed',
  `out_index` int(11) DEFAULT '0',
  `create_transaction_id` int(20) DEFAULT NULL,
  `spend_transaction_id` int(20) DEFAULT NULL,
  `amount` double DEFAULT '0',
  `coin_blocks_created` bigint(20) DEFAULT NULL,
  `coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `create_block_id` int(20) DEFAULT NULL,
  `spend_block_id` int(20) DEFAULT NULL,
  `payout_io_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(20) NOT NULL,
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
  `last_active` int(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_games`
--

CREATE TABLE `user_games` (
  `user_game_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `strategy_id` int(11) DEFAULT NULL,
  `account_value` decimal(10,8) NOT NULL DEFAULT '0.00000000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_messages`
--

CREATE TABLE `user_messages` (
  `message_id` int(20) NOT NULL,
  `from_user_id` int(20) DEFAULT NULL,
  `to_user_id` int(20) DEFAULT NULL,
  `message` text NOT NULL,
  `send_time` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_resettokens`
--

CREATE TABLE `user_resettokens` (
  `token_id` int(20) NOT NULL,
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
  `completed` int(2) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` int(22) NOT NULL,
  `login_type` enum('default','superuser') COLLATE latin1_german2_ci NOT NULL DEFAULT 'default',
  `user_id` int(20) NOT NULL DEFAULT '0',
  `session_key` varchar(32) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `login_time` int(12) NOT NULL DEFAULT '0',
  `logout_time` int(12) NOT NULL DEFAULT '0',
  `expire_time` int(12) NOT NULL DEFAULT '0',
  `ip_address` varchar(30) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_strategies`
--

CREATE TABLE `user_strategies` (
  `strategy_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `transaction_fee` bigint(20) NOT NULL DEFAULT '100000',
  `voting_strategy` enum('manual','by_rank','by_nation','api','') NOT NULL DEFAULT 'manual',
  `aggregate_threshold` int(11) NOT NULL DEFAULT '50',
  `by_rank_ranks` varchar(100) NOT NULL DEFAULT '',
  `api_url` varchar(255) NOT NULL DEFAULT '',
  `min_votesum_pct` int(11) NOT NULL DEFAULT '0',
  `max_votesum_pct` int(11) NOT NULL DEFAULT '100',
  `min_coins_available` decimal(10,8) NOT NULL DEFAULT '0.00000000',
  `nation_pct_1` int(11) NOT NULL DEFAULT '0',
  `nation_pct_2` int(11) NOT NULL DEFAULT '0',
  `nation_pct_3` int(11) NOT NULL DEFAULT '0',
  `nation_pct_4` int(11) NOT NULL DEFAULT '0',
  `nation_pct_5` int(11) NOT NULL DEFAULT '0',
  `nation_pct_6` int(11) NOT NULL DEFAULT '0',
  `nation_pct_7` int(11) NOT NULL DEFAULT '0',
  `nation_pct_8` int(11) NOT NULL DEFAULT '0',
  `nation_pct_9` int(11) NOT NULL DEFAULT '0',
  `nation_pct_10` int(11) NOT NULL DEFAULT '0',
  `nation_pct_11` int(11) NOT NULL DEFAULT '0',
  `nation_pct_12` int(11) NOT NULL DEFAULT '0',
  `nation_pct_13` int(11) NOT NULL DEFAULT '0',
  `nation_pct_14` int(11) NOT NULL DEFAULT '0',
  `nation_pct_15` int(11) NOT NULL DEFAULT '0',
  `nation_pct_16` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewers`
--

CREATE TABLE `viewers` (
  `viewer_id` int(20) NOT NULL,
  `account_id` int(20) NOT NULL DEFAULT '0',
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_connections`
--

CREATE TABLE `viewer_connections` (
  `connection_id` int(20) NOT NULL,
  `type` enum('viewer2viewer','viewer2user') COLLATE latin1_german2_ci NOT NULL,
  `from_id` int(20) NOT NULL,
  `to_id` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_identifiers`
--

CREATE TABLE `viewer_identifiers` (
  `identifier_id` int(11) NOT NULL,
  `viewer_id` int(20) NOT NULL DEFAULT '0',
  `type` enum('ip','cookie') NOT NULL,
  `identifier` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(20) NOT NULL,
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
  `nation_id` int(11) DEFAULT NULL,
  `vote_transaction_id` int(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  `game_id` int(11) NOT NULL DEFAULT '1',
  `bet_round_id` int(11) DEFAULT NULL,
  `ref_block_id` bigint(20) DEFAULT NULL,
  `ref_coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_strategy_blocks`
--

CREATE TABLE `user_strategy_blocks` (
  `strategy_block_id` int(20) NOT NULL,
  `strategy_id` int(20) DEFAULT NULL,
  `block_within_round` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD UNIQUE KEY `address` (`address`,`game_id`),
  ADD KEY `nation_id` (`nation_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `is_mine` (`is_mine`),
  ADD KEY `bet_round_id` (`bet_round_id`),
  ADD KEY `bet_nation_id` (`bet_nation_id`);

--
-- Indexes for table `async_email_deliveries`
--
ALTER TABLE `async_email_deliveries`
  ADD PRIMARY KEY (`delivery_id`);

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`internal_block_id`),
  ADD UNIQUE KEY `block_id_2` (`block_id`,`game_id`),
  ADD UNIQUE KEY `block_hash` (`block_hash`),
  ADD KEY `miner_user_id` (`miner_user_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `block_id` (`block_id`);

--
-- Indexes for table `browsers`
--
ALTER TABLE `browsers`
  ADD PRIMARY KEY (`browser_id`);

--
-- Indexes for table `browserstrings`
--
ALTER TABLE `browserstrings`
  ADD PRIMARY KEY (`browserstring_id`),
  ADD KEY `v1` (`viewer_id`),
  ADD KEY `b1` (`browser_id`);

--
-- Indexes for table `cached_rounds`
--
ALTER TABLE `cached_rounds`
  ADD PRIMARY KEY (`internal_round_id`),
  ADD UNIQUE KEY `round_id_2` (`round_id`,`game_id`),
  ADD KEY `winning_nation_id` (`winning_nation_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `round_id` (`round_id`),
  ADD KEY `payout_block_id` (`payout_block_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `game_type` (`game_type`),
  ADD KEY `game_status` (`game_status`),
  ADD KEY `payout_weight` (`payout_weight`);

--
-- Indexes for table `game_nations`
--
ALTER TABLE `game_nations`
  ADD PRIMARY KEY (`game_nation_id`),
  ADD UNIQUE KEY `game_id` (`game_id`,`nation_id`);

--
-- Indexes for table `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD UNIQUE KEY `invitation_key` (`invitation_key`),
  ADD KEY `inviter_id` (`inviter_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `used` (`used`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`match_id`),
  ADD KEY `match_type_id` (`match_type_id`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `match_IOs`
--
ALTER TABLE `match_IOs`
  ADD PRIMARY KEY (`io_id`),
  ADD KEY `membership_id` (`membership_id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `spend_status` (`spend_status`),
  ADD KEY `create_move_id` (`create_move_id`),
  ADD KEY `spend_move_id` (`spend_move_id`);

--
-- Indexes for table `match_memberships`
--
ALTER TABLE `match_memberships`
  ADD PRIMARY KEY (`membership_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `player_position` (`player_position`);

--
-- Indexes for table `match_messages`
--
ALTER TABLE `match_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`),
  ADD KEY `hide_user_id` (`hide_user_id`);

--
-- Indexes for table `match_moves`
--
ALTER TABLE `match_moves`
  ADD PRIMARY KEY (`move_id`),
  ADD KEY `membership_id` (`membership_id`),
  ADD KEY `round_number` (`round_number`);

--
-- Indexes for table `match_rounds`
--
ALTER TABLE `match_rounds`
  ADD PRIMARY KEY (`match_round_id`),
  ADD KEY `status` (`status`),
  ADD KEY `round_number` (`round_number`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `winning_membership_id` (`winning_membership_id`);

--
-- Indexes for table `match_types`
--
ALTER TABLE `match_types`
  ADD PRIMARY KEY (`match_type_id`);

--
-- Indexes for table `nations`
--
ALTER TABLE `nations`
  ADD PRIMARY KEY (`nation_id`),
  ADD UNIQUE KEY `vote_id` (`vote_id`),
  ADD KEY `address_character` (`address_character`);

--
-- Indexes for table `pageviews`
--
ALTER TABLE `pageviews`
  ADD PRIMARY KEY (`pageview_id`),
  ADD KEY `viewer_id` (`viewer_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `browserstring_id` (`browserstring_id`),
  ADD KEY `ip_id` (`ip_id`),
  ADD KEY `cookie_id` (`cookie_id`),
  ADD KEY `pv_page_id` (`pv_page_id`);

--
-- Indexes for table `page_urls`
--
ALTER TABLE `page_urls`
  ADD PRIMARY KEY (`page_url_id`),
  ADD UNIQUE KEY `url` (`url`) USING BTREE;

--
-- Indexes for table `redirect_urls`
--
ALTER TABLE `redirect_urls`
  ADD PRIMARY KEY (`redirect_url_id`),
  ADD UNIQUE KEY `url` (`url`);

--
-- Indexes for table `site_constants`
--
ALTER TABLE `site_constants`
  ADD PRIMARY KEY (`constant_id`),
  ADD UNIQUE KEY `constant_name` (`constant_name`);

--
-- Indexes for table `transaction_IOs`
--
ALTER TABLE `transaction_IOs`
  ADD PRIMARY KEY (`io_id`),
  ADD UNIQUE KEY `create_transaction_id_2` (`create_transaction_id`,`out_index`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `nation_id` (`nation_id`),
  ADD KEY `instantly_mature` (`instantly_mature`),
  ADD KEY `spend_status` (`spend_status`),
  ADD KEY `create_transaction_id` (`create_transaction_id`),
  ADD KEY `spend_transaction_id` (`spend_transaction_id`),
  ADD KEY `create_block_id` (`create_block_id`),
  ADD KEY `spend_block_id` (`spend_block_id`),
  ADD KEY `payout_io_id` (`payout_io_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `api_access_code` (`api_access_code`),
  ADD KEY `password` (`password`);

--
-- Indexes for table `user_games`
--
ALTER TABLE `user_games`
  ADD PRIMARY KEY (`user_game_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`game_id`),
  ADD KEY `strategy_id` (`strategy_id`);

--
-- Indexes for table `user_messages`
--
ALTER TABLE `user_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`);

--
-- Indexes for table `user_resettokens`
--
ALTER TABLE `user_resettokens`
  ADD PRIMARY KEY (`token_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `user_strategies`
--
ALTER TABLE `user_strategies`
  ADD PRIMARY KEY (`strategy_id`);

--
-- Indexes for table `viewers`
--
ALTER TABLE `viewers`
  ADD PRIMARY KEY (`viewer_id`);

--
-- Indexes for table `viewer_connections`
--
ALTER TABLE `viewer_connections`
  ADD PRIMARY KEY (`connection_id`);

--
-- Indexes for table `viewer_identifiers`
--
ALTER TABLE `viewer_identifiers`
  ADD PRIMARY KEY (`identifier_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `user_id` (`from_user_id`),
  ADD KEY `block_id` (`block_id`),
  ADD KEY `nation_id` (`nation_id`),
  ADD KEY `vote_transaction_id` (`vote_transaction_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `tx_hash` (`tx_hash`);

--
-- Indexes for table `user_strategy_blocks`
--
ALTER TABLE `user_strategy_blocks`
  ADD PRIMARY KEY (`strategy_block_id`),
  ADD UNIQUE KEY `strategy_id` (`strategy_id`,`block_within_round`),
  ADD INDEX (`strategy_id`),
  ADD INDEX (`block_within_round`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `async_email_deliveries`
--
ALTER TABLE `async_email_deliveries`
  MODIFY `delivery_id` int(12) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `internal_block_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `browsers`
--
ALTER TABLE `browsers`
  MODIFY `browser_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `browserstrings`
--
ALTER TABLE `browserstrings`
  MODIFY `browserstring_id` int(30) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `cached_rounds`
--
ALTER TABLE `cached_rounds`
  MODIFY `internal_round_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `game_nations`
--
ALTER TABLE `game_nations`
  MODIFY `game_nation_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `invitations`
--
ALTER TABLE `invitations`
  MODIFY `invitation_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `match_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `match_IOs`
--
ALTER TABLE `match_IOs`
  MODIFY `io_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `match_memberships`
--
ALTER TABLE `match_memberships`
  MODIFY `membership_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `match_messages`
--
ALTER TABLE `match_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `match_moves`
--
ALTER TABLE `match_moves`
  MODIFY `move_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `match_rounds`
--
ALTER TABLE `match_rounds`
  MODIFY `match_round_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `match_types`
--
ALTER TABLE `match_types`
  MODIFY `match_type_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `nations`
--
ALTER TABLE `nations`
  MODIFY `nation_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `pageviews`
--
ALTER TABLE `pageviews`
  MODIFY `pageview_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `page_urls`
--
ALTER TABLE `page_urls`
  MODIFY `page_url_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `redirect_urls`
--
ALTER TABLE `redirect_urls`
  MODIFY `redirect_url_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `site_constants`
--
ALTER TABLE `site_constants`
  MODIFY `constant_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `transaction_IOs`
--
ALTER TABLE `transaction_IOs`
  MODIFY `io_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_games`
--
ALTER TABLE `user_games`
  MODIFY `user_game_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_messages`
--
ALTER TABLE `user_messages`
  MODIFY `message_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_resettokens`
--
ALTER TABLE `user_resettokens`
  MODIFY `token_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `session_id` int(22) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_strategies`
--
ALTER TABLE `user_strategies`
  MODIFY `strategy_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `viewers`
--
ALTER TABLE `viewers`
  MODIFY `viewer_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `viewer_connections`
--
ALTER TABLE `viewer_connections`
  MODIFY `connection_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `viewer_identifiers`
--
ALTER TABLE `viewer_identifiers`
  MODIFY `identifier_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `user_strategy_blocks`
--
ALTER TABLE `user_strategy_blocks`
  MODIFY `strategy_block_id` int(20) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
