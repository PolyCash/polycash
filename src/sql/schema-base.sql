SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `primary_blockchain_id` int(11) DEFAULT NULL,
  `is_mine` tinyint(1) NOT NULL DEFAULT '0',
  `is_destroy_address` tinyint(1) NOT NULL DEFAULT '0',
  `is_separator_address` tinyint(4) NOT NULL DEFAULT '0',
  `is_passthrough_address` tinyint(1) NOT NULL DEFAULT '0',
  `vote_identifier` varchar(20) NOT NULL DEFAULT '',
  `option_index` int(11) DEFAULT NULL,
  `address` varchar(50) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `address_keys`
--

CREATE TABLE `address_keys` (
  `address_key_id` int(11) NOT NULL,
  `address_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `option_index` int(11) DEFAULT NULL,
  `primary_blockchain_id` int(11) DEFAULT NULL,
  `address_set_id` int(11) DEFAULT NULL,
  `access_key` varchar(32) DEFAULT NULL,
  `pub_key` varchar(40) NOT NULL DEFAULT '',
  `priv_key` varchar(255) DEFAULT NULL,
  `priv_enc` varchar(300) NOT NULL DEFAULT '',
  `associated_email_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `address_sets`
--

CREATE TABLE `address_sets` (
  `address_set_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `applied` tinyint(1) NOT NULL DEFAULT '0',
  `has_option_indices_until` int(11) DEFAULT NULL
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
  `delivery_key` varchar(16) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_delivered` int(20) NOT NULL DEFAULT '0',
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `sendgrid_response` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blockchains`
--

CREATE TABLE `blockchains` (
  `blockchain_id` int(11) NOT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `only_game_id` int(11) DEFAULT NULL,
  `default_image_id` int(11) DEFAULT NULL,
  `authoritative_peer_id` int(11) DEFAULT NULL,
  `blockchain_name` varchar(100) NOT NULL DEFAULT '',
  `url_identifier` varchar(100) NOT NULL DEFAULT '',
  `online` tinyint(1) NOT NULL DEFAULT '1',
  `p2p_mode` enum('none','rpc','web_api') NOT NULL DEFAULT 'none',
  `coin_name` varchar(100) NOT NULL DEFAULT '',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT '',
  `seconds_per_block` int(11) DEFAULT NULL,
  `average_seconds_per_block` float DEFAULT NULL,
  `decimal_places` int(11) NOT NULL DEFAULT '8',
  `rpc_host` varchar(255) DEFAULT NULL,
  `rpc_username` varchar(100) DEFAULT NULL,
  `rpc_password` varchar(100) DEFAULT NULL,
  `rpc_port` int(11) DEFAULT NULL,
  `default_rpc_port` int(11) DEFAULT NULL,
  `first_required_block` int(11) DEFAULT NULL,
  `last_complete_block` int(11) DEFAULT NULL,
  `load_unconfirmed_transactions` tinyint(1) NOT NULL DEFAULT '1',
  `initial_pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `supports_getblockheader` tinyint(1) NOT NULL DEFAULT '0',
  `last_hash_time` int(11) DEFAULT NULL,
  `rpc_last_time_connected` int(11) DEFAULT NULL,
  `block_height` int(11) DEFAULT NULL,
  `genesis_address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `blockchains`
--

INSERT INTO `blockchains` (`blockchain_id`, `creator_id`, `only_game_id`, `default_image_id`, `authoritative_peer_id`, `blockchain_name`, `url_identifier`, `online`, `p2p_mode`, `coin_name`, `coin_name_plural`, `seconds_per_block`, `average_seconds_per_block`, `decimal_places`, `rpc_host`, `rpc_username`, `rpc_password`, `rpc_port`, `default_rpc_port`, `first_required_block`, `last_complete_block`, `load_unconfirmed_transactions`, `initial_pow_reward`, `supports_getblockheader`, `last_hash_time`, `rpc_last_time_connected`, `block_height`, `genesis_address`) VALUES
(1, NULL, NULL, 35, NULL, 'Bitcoin', 'bitcoin', 1, 'rpc', 'bitcoin', 'bitcoins', 600, NULL, 8, '127.0.0.1', NULL, NULL, NULL, 8332, NULL, NULL, 0, 5000000000, 1, NULL, NULL, NULL, NULL),
(2, NULL, NULL, 73, NULL, 'Litecoin', 'litecoin', 1, 'rpc', 'litecoin', 'litecoins', 150, NULL, 8, '127.0.0.1', NULL, NULL, NULL, 9332, NULL, NULL, 1, 5000000000, 0, NULL, NULL, NULL, NULL),
(3, NULL, NULL, 77, 1, 'StakeChain', 'stakechain', 1, 'web_api', 'stake', 'stakes', 30, 45, 8, '127.0.0.1', NULL, NULL, NULL, NULL, 0, NULL, 1, 10000000000, 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `internal_block_id` int(11) NOT NULL,
  `blockchain_id` int(11) DEFAULT NULL,
  `block_id` int(11) DEFAULT NULL,
  `block_hash` varchar(100) DEFAULT NULL,
  `miner_user_id` int(20) DEFAULT NULL,
  `num_transactions` int(11) DEFAULT NULL,
  `num_ios_in` int(11) DEFAULT NULL,
  `num_ios_out` int(11) DEFAULT NULL,
  `sum_coins_in` bigint(20) DEFAULT NULL,
  `sum_coins_out` bigint(20) DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_loaded` int(11) DEFAULT NULL,
  `time_mined` int(11) DEFAULT NULL,
  `locally_saved` tinyint(1) NOT NULL DEFAULT '0',
  `load_time` float NOT NULL DEFAULT '0',
  `sec_since_prev_block` int(11) DEFAULT NULL,
  `transactions_html` mediumblob,
  `json_transactions` mediumblob
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `cached_urls`
--

CREATE TABLE `cached_urls` (
  `cached_url_id` int(11) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `cached_result` longtext,
  `time_created` int(11) DEFAULT NULL,
  `time_fetched` int(11) DEFAULT NULL,
  `load_time` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `cards`
--

CREATE TABLE `cards` (
  `card_id` int(20) NOT NULL,
  `peer_card_id` int(20) DEFAULT NULL,
  `group_id` int(20) NOT NULL,
  `design_id` int(20) DEFAULT NULL,
  `peer_id` int(11) DEFAULT NULL,
  `unlock_time` int(20) DEFAULT NULL,
  `mint_time` int(20) NOT NULL,
  `currency_id` int(20) NOT NULL DEFAULT '1',
  `fv_currency_id` int(11) DEFAULT NULL,
  `default_game_id` int(11) DEFAULT NULL,
  `amount` float NOT NULL,
  `purity` int(8) NOT NULL DEFAULT '100',
  `secret` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `secret_hash` varchar(100) COLLATE latin1_german2_ci DEFAULT NULL,
  `status` enum('issued','printed','assigned','sold','redeemed','canceled','claimed') COLLATE latin1_german2_ci NOT NULL,
  `redeem_time` int(20) NOT NULL DEFAULT '0',
  `claim_time` int(11) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `card_user_id` int(20) DEFAULT NULL,
  `card_group_id` int(12) DEFAULT NULL,
  `reseller_sale_id` int(20) DEFAULT NULL,
  `io_tx_hash` varchar(64) COLLATE latin1_german2_ci DEFAULT NULL,
  `io_out_index` int(11) DEFAULT NULL,
  `io_id` int(11) DEFAULT NULL,
  `redemption_tx_hash` varchar(64) COLLATE latin1_german2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_conversions`
--

CREATE TABLE `card_conversions` (
  `conversion_id` int(20) NOT NULL,
  `card_id` int(20) NOT NULL,
  `currency1_id` int(11) DEFAULT NULL,
  `currency2_id` int(11) DEFAULT NULL,
  `withdrawal_id` int(11) DEFAULT NULL,
  `group_withdrawal_id` int(11) DEFAULT NULL,
  `currency1_delta` double NOT NULL,
  `currency2_delta` double NOT NULL,
  `reason` enum('conversion','withdrawal','group_withdrawal','nonneg_conversion') COLLATE latin1_german2_ci NOT NULL DEFAULT 'conversion',
  `time_created` int(20) NOT NULL,
  `ip_address` varchar(40) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_currency_balances`
--

CREATE TABLE `card_currency_balances` (
  `balance_id` int(11) NOT NULL,
  `card_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `balance` double NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `card_currency_denominations`
--

CREATE TABLE `card_currency_denominations` (
  `denomination_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `fv_currency_id` int(11) DEFAULT NULL,
  `denomination` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `card_currency_denominations`
--

INSERT INTO `card_currency_denominations` (`denomination_id`, `currency_id`, `fv_currency_id`, `denomination`) VALUES
(1, 6, 1, '1'),
(2, 6, 1, '5'),
(3, 6, 1, '20'),
(4, 6, 1, '50'),
(5, 6, 1, '100'),
(6, 6, 1, '500'),
(7, 6, 6, '0.01'),
(8, 6, 6, '0.05'),
(9, 6, 6, '0.1'),
(10, 6, 6, '0.5'),
(11, 6, 6, '1'),
(12, 6, 6, '2'),
(13, 6, 6, '5'),
(14, 16, 16, '1'),
(15, 16, 16, '5'),
(16, 16, 16, '10'),
(17, 16, 16, '50'),
(18, 16, 16, '20');

-- --------------------------------------------------------

--
-- Table structure for table `card_designs`
--

CREATE TABLE `card_designs` (
  `design_id` int(11) NOT NULL,
  `user_id` int(20) NOT NULL DEFAULT '0',
  `image_id` int(20) DEFAULT NULL,
  `status` enum('pending','printed','canceled','') COLLATE latin1_german2_ci NOT NULL DEFAULT 'pending',
  `denomination_id` int(11) DEFAULT NULL,
  `display_name` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `display_title` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `display_email` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `display_pnum` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `redeem_url` varchar(255) COLLATE latin1_german2_ci DEFAULT NULL,
  `time_created` int(20) NOT NULL DEFAULT '0',
  `purity` varchar(20) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `text_color` varchar(100) COLLATE latin1_german2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_failedchecks`
--

CREATE TABLE `card_failedchecks` (
  `check_id` int(20) NOT NULL,
  `card_id` int(20) DEFAULT NULL,
  `ip_address` varchar(100) NOT NULL DEFAULT '',
  `check_time` int(20) NOT NULL DEFAULT '0',
  `attempted_code` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `card_printrequests`
--

CREATE TABLE `card_printrequests` (
  `request_id` int(20) NOT NULL,
  `design_id` int(20) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `address_id` int(20) NOT NULL DEFAULT '0',
  `card_group_id` int(20) DEFAULT NULL,
  `peer_id` int(11) DEFAULT NULL,
  `how_many` int(20) NOT NULL DEFAULT '0',
  `print_status` enum('not-printed','printed','canceled') COLLATE latin1_german2_ci NOT NULL DEFAULT 'not-printed',
  `pay_status` enum('not-received','received') COLLATE latin1_german2_ci NOT NULL DEFAULT 'not-received',
  `secrets_present` tinyint(1) NOT NULL DEFAULT '0',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_payment_sent` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_sessions`
--

CREATE TABLE `card_sessions` (
  `session_id` int(22) NOT NULL,
  `card_user_id` int(20) NOT NULL DEFAULT '0',
  `login_type` enum('default','superuser') COLLATE latin1_german2_ci NOT NULL DEFAULT 'default',
  `session_key` varchar(32) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `login_time` int(12) NOT NULL DEFAULT '0',
  `logout_time` int(12) DEFAULT NULL,
  `expire_time` int(12) NOT NULL DEFAULT '0',
  `ip_address` varchar(30) COLLATE latin1_german2_ci NOT NULL,
  `synchronizer_token` varchar(32) COLLATE latin1_german2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_status_changes`
--

CREATE TABLE `card_status_changes` (
  `change_id` int(11) NOT NULL,
  `card_id` int(20) NOT NULL DEFAULT '0',
  `from_status` varchar(20) COLLATE latin1_german2_ci NOT NULL,
  `to_status` varchar(20) COLLATE latin1_german2_ci NOT NULL,
  `change_time` varchar(20) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_users`
--

CREATE TABLE `card_users` (
  `card_user_id` int(20) NOT NULL,
  `card_id` int(20) NOT NULL DEFAULT '0',
  `create_time` int(20) NOT NULL DEFAULT '0',
  `create_ip` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `password` varchar(100) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card_withdrawals`
--

CREATE TABLE `card_withdrawals` (
  `withdrawal_id` int(20) NOT NULL,
  `withdraw_method` enum('blockchain','mobilemoney','card_account') COLLATE latin1_german2_ci NOT NULL,
  `withdraw_time` int(20) NOT NULL,
  `card_id` int(20) NOT NULL,
  `card_user_id` int(20) NOT NULL DEFAULT '0',
  `status_change_id` int(20) NOT NULL DEFAULT '0',
  `currency_id` int(11) DEFAULT NULL,
  `message` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `tx_hash` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `amount` double NOT NULL,
  `to_address` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `ip_address` varchar(50) COLLATE latin1_german2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `url_identifier` varchar(100) DEFAULT NULL,
  `category_name` varchar(255) DEFAULT NULL,
  `category_level` int(11) DEFAULT NULL,
  `display_rank` float NOT NULL DEFAULT '0',
  `icon_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `parent_category_id`, `url_identifier`, `category_name`, `category_level`, `display_rank`, `icon_name`) VALUES
(1, NULL, 'virtual-sports', 'Virtual Sports', 0, 3, 'trophy'),
(2, NULL, 'sports', 'Sports Betting', 0, 1, 'soccer-ball-o'),
(3, NULL, 'esports', 'eSports', 0, 2, 'gamepad'),
(4, NULL, 'assets', 'Financial Assets', 0, 4, 'line-chart'),
(5, 3, 'call-of-duty', 'Call of Duty', 1, 1, NULL),
(6, 3, 'counterstrike', 'Counter-Strike', 1, 2, NULL),
(7, 3, 'crossfire', 'Crossfire', 1, 3, NULL),
(8, 3, 'dota-2', 'Dota 2', 1, 4, NULL),
(9, 3, 'halo', 'Halo', 1, 5, NULL),
(10, 3, 'hearthstone', 'Hearthstone', 1, 6, NULL),
(11, 3, 'lol', 'League of Legends', 1, 7, NULL),
(12, 3, 'overwatch', 'Overwatch', 1, 8, NULL),
(13, 3, 'quake-champions', 'Quake Champions', 1, 9, NULL),
(14, 3, 'rocket-league', 'Rocket League', 1, 10, NULL),
(15, 3, 'smite', 'Smite', 1, 11, NULL),
(16, 3, 'starcraft-2', 'StarCraft II', 1, 12, NULL),
(17, 3, 'vainglory', 'Vainglory', 1, 13, NULL),
(18, 3, 'world-of-tanks', 'World of Tanks', 1, 14, NULL),
(19, 2, 'baseball', 'Baseball', 1, 1, NULL),
(20, 2, 'basketball', 'Basketball', 1, 2, NULL),
(21, 2, 'boxing', 'Boxing', 1, 3, NULL),
(22, 2, 'cricket', 'Cricket', 1, 4, NULL),
(23, 2, 'football', 'Football', 1, 5, NULL),
(24, 2, 'golf', 'Golf', 1, 6, NULL),
(25, 2, 'horse-racing', 'Horse Racing', 1, 7, NULL),
(26, 2, 'ice-hockey', 'Ice Hockey', 1, 8, NULL),
(27, 2, 'mma', 'Mixed Martial Arts', 1, 9, NULL),
(28, 2, 'motor-sports', 'Motor Sports', 1, 10, NULL),
(29, 2, 'soccer', 'Soccer', 1, 11, NULL),
(30, 2, 'tennis', 'Tennis', 1, 12, NULL),
(31, NULL, 'strategy-games', 'Strategy Games', 0, 0, 'flag');

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `currency_id` int(11) NOT NULL,
  `oracle_url_id` int(11) DEFAULT NULL,
  `blockchain_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `default_design_image_id` int(11) DEFAULT NULL,
  `default_design_text_color` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_name` varchar(100) NOT NULL DEFAULT '',
  `short_name_plural` varchar(100) NOT NULL DEFAULT '',
  `abbreviation` varchar(10) NOT NULL DEFAULT '',
  `symbol` varchar(10) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`currency_id`, `oracle_url_id`, `blockchain_id`, `entity_id`, `default_design_image_id`, `default_design_text_color`, `name`, `short_name`, `short_name_plural`, `abbreviation`, `symbol`) VALUES
(1, 3, NULL, NULL, NULL, NULL, 'US Dollar', 'dollar', 'dollars', 'USD', '$'),
(2, NULL, NULL, NULL, NULL, NULL, 'Euro', 'euro', 'euros', 'EUR', '&euro;'),
(3, NULL, NULL, NULL, NULL, NULL, 'Renminbi', 'renminbi', 'renminbi', 'CNY', '&yen;'),
(4, NULL, NULL, NULL, NULL, NULL, 'Pound sterling', 'pound', 'pounds', 'GBP', '&pound;'),
(5, NULL, NULL, NULL, NULL, NULL, 'Japanese yen', 'yen', 'yen', 'JPY', '&yen;'),
(6, NULL, 1, NULL, 80, NULL, 'Bitcoin', 'bitcoin', 'bitcoins', 'BTC', '&#3647;'),
(7, NULL, 2, NULL, NULL, NULL, 'Litecoin', 'litecoin', 'litecoins', 'LTC', 'L'),
(8, NULL, NULL, NULL, NULL, NULL, 'Bitcoin Cash', 'bcash', 'bitcoins', 'BCH', ''),
(9, NULL, NULL, NULL, NULL, NULL, 'Dash', 'dash', 'dash', 'DASH', ''),
(10, NULL, NULL, NULL, NULL, NULL, 'Ethereum', 'ether', 'ether', 'ETH', ''),
(11, NULL, NULL, NULL, NULL, NULL, 'Ethereum Classic', 'ether', 'ether', 'ETC', ''),
(12, NULL, NULL, NULL, NULL, NULL, 'Monero', 'monero', 'monero', 'XMR', ''),
(13, NULL, NULL, NULL, NULL, NULL, 'NEM', 'xem', 'xem', 'XEM', ''),
(14, NULL, NULL, NULL, NULL, NULL, 'NEO', 'neo', 'neo', 'NEO', ''),
(15, NULL, NULL, NULL, NULL, NULL, 'Ripple', 'ripple', 'ripples', 'XRP', ''),
(16, NULL, NULL, NULL, NULL, NULL, 'EOS', 'EOS', 'EOS', 'EOS', ''),
(17, NULL, NULL, NULL, NULL, NULL, 'Amazon', 'AMZN', 'AMZN', 'AMZN', ''),
(18, NULL, NULL, NULL, NULL, NULL, 'Apple', 'AAPL', 'AAPL', 'AAPL', ''),
(19, NULL, NULL, NULL, NULL, NULL, 'Facebook', 'FB', 'FB', 'FB', ''),
(20, NULL, NULL, NULL, NULL, NULL, 'Google', 'GOOG', 'GOOG', 'GOOG', ''),
(21, NULL, NULL, NULL, NULL, NULL, 'HP', 'HP', 'HP', 'HP', ''),
(22, NULL, NULL, NULL, NULL, NULL, 'IBM', 'IBM', 'IBM', 'IBM', ''),
(23, NULL, NULL, NULL, NULL, NULL, 'Intel', 'INTC', 'INTC', 'INTC', ''),
(24, NULL, NULL, NULL, NULL, NULL, 'Microsoft', 'MSFT', 'MSFT', 'MSFT', ''),
(25, NULL, NULL, NULL, NULL, NULL, 'Netflix', 'NFLX', 'NFLX', 'NFLX', ''),
(26, NULL, NULL, NULL, NULL, NULL, 'Salesforce', 'CRM', 'CRM', 'CRM', ''),
(27, NULL, NULL, NULL, NULL, NULL, 'Twitter', 'TWTR', 'TWTR', 'TWTR', ''),
(28, NULL, NULL, NULL, NULL, NULL, 'Uber', 'UBER', 'UBER', 'UBER', ''),
(29, NULL, 3, NULL, NULL, NULL, 'StakeChain', 'stake', 'stakes', 'stakes', '');

-- --------------------------------------------------------

--
-- Table structure for table `currency_accounts`
--

CREATE TABLE `currency_accounts` (
  `account_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `current_address_id` int(11) DEFAULT NULL,
  `is_faucet` tinyint(1) NOT NULL DEFAULT '0',
  `is_game_sale_account` tinyint(1) NOT NULL DEFAULT '0',
  `is_blockchain_sale_account` tinyint(1) DEFAULT '0',
  `is_escrow_account` tinyint(1) NOT NULL DEFAULT '0',
  `account_name` varchar(100) NOT NULL,
  `time_created` int(11) DEFAULT NULL,
  `has_option_indices_until` int(11) DEFAULT '-1',
  `last_notified_account_value` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currency_invoices`
--

CREATE TABLE `currency_invoices` (
  `invoice_id` int(11) NOT NULL,
  `user_game_id` int(11) DEFAULT NULL,
  `pay_currency_id` int(11) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `receive_address_id` int(11) DEFAULT NULL,
  `invoice_type` enum('join_buyin','buyin','sale_buyin','sellout','') NOT NULL DEFAULT '',
  `status` enum('unpaid','unconfirmed','confirmed','settled','pending_refund','refunded') NOT NULL DEFAULT 'unpaid',
  `invoice_key_string` varchar(64) NOT NULL DEFAULT '',
  `buyin_amount` decimal(16,8) DEFAULT NULL,
  `color_amount` decimal(16,8) DEFAULT NULL,
  `pay_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `fee_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `confirmed_amount_paid` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `unconfirmed_amount_paid` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_confirmed` int(20) NOT NULL DEFAULT '0',
  `expire_time` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currency_invoice_ios`
--

CREATE TABLE `currency_invoice_ios` (
  `invoice_io_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `tx_hash` varchar(64) DEFAULT NULL,
  `out_index` int(11) DEFAULT NULL,
  `game_out_index` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `currency_prices`
--

CREATE TABLE `currency_prices` (
  `price_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `reference_currency_id` int(11) DEFAULT NULL,
  `cached_url_id` int(11) DEFAULT NULL,
  `price` float NOT NULL DEFAULT '0',
  `time_added` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `currency_prices`
--

INSERT INTO `currency_prices` (`price_id`, `currency_id`, `reference_currency_id`, `cached_url_id`, `price`, `time_added`) VALUES
(1, 6, 6, NULL, 1, 1561602626);

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE `entities` (
  `entity_id` int(11) NOT NULL,
  `entity_type_id` int(11) DEFAULT NULL,
  `default_image_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `entity_name` varchar(255) NOT NULL DEFAULT '',
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `last_name` varchar(50) NOT NULL DEFAULT '',
  `electoral_votes` int(11) NOT NULL DEFAULT '0',
  `image_url` varchar(255) DEFAULT NULL,
  `content_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `entities`
--

INSERT INTO `entities` (`entity_id`, `entity_type_id`, `default_image_id`, `currency_id`, `entity_name`, `first_name`, `last_name`, `electoral_votes`, `image_url`, `content_url`) VALUES
(1, 1, 17, NULL, 'Bernie Sanders', 'Bernie', 'Sanders', 0, NULL, NULL),
(2, 1, 18, NULL, 'Donald Trump', 'Donald', 'Trump', 0, NULL, NULL),
(3, 1, 19, NULL, 'Hillary Clinton', 'Hillary', 'Clinton', 0, NULL, NULL),
(5, 2, NULL, NULL, 'Alabama', '', '', 9, NULL, NULL),
(6, 2, NULL, NULL, 'Alaska', '', '', 3, NULL, NULL),
(7, 2, NULL, NULL, 'Arizona', '', '', 11, NULL, NULL),
(8, 2, NULL, NULL, 'Arkansas', '', '', 6, NULL, NULL),
(9, 2, NULL, NULL, 'California', '', '', 55, NULL, NULL),
(10, 2, NULL, NULL, 'Colorado', '', '', 9, NULL, NULL),
(11, 2, NULL, NULL, 'Connecticut', '', '', 7, NULL, NULL),
(12, 2, NULL, NULL, 'Delaware', '', '', 3, NULL, NULL),
(13, 2, NULL, NULL, 'Florida', '', '', 29, NULL, NULL),
(14, 2, NULL, NULL, 'Georgia', '', '', 16, NULL, NULL),
(15, 2, NULL, NULL, 'Hawaii', '', '', 4, NULL, NULL),
(16, 2, NULL, NULL, 'Idaho', '', '', 4, NULL, NULL),
(17, 2, NULL, NULL, 'Illinois', '', '', 20, NULL, NULL),
(18, 2, NULL, NULL, 'Indiana', '', '', 11, NULL, NULL),
(19, 2, NULL, NULL, 'Iowa', '', '', 6, NULL, NULL),
(20, 2, NULL, NULL, 'Kansas', '', '', 6, NULL, NULL),
(21, 2, NULL, NULL, 'Kentucky', '', '', 8, NULL, NULL),
(22, 2, NULL, NULL, 'Louisiana', '', '', 8, NULL, NULL),
(23, 2, NULL, NULL, 'Maine', '', '', 4, NULL, NULL),
(24, 2, NULL, NULL, 'Maryland', '', '', 10, NULL, NULL),
(25, 2, NULL, NULL, 'Massachusetts', '', '', 11, NULL, NULL),
(26, 2, NULL, NULL, 'Michigan', '', '', 16, NULL, NULL),
(27, 2, NULL, NULL, 'Minnesota', '', '', 10, NULL, NULL),
(28, 2, NULL, NULL, 'Mississippi', '', '', 6, NULL, NULL),
(29, 2, NULL, NULL, 'Missouri', '', '', 10, NULL, NULL),
(30, 2, NULL, NULL, 'Montana', '', '', 3, NULL, NULL),
(31, 2, NULL, NULL, 'Nebraska', '', '', 5, NULL, NULL),
(32, 2, NULL, NULL, 'Nevada', '', '', 6, NULL, NULL),
(33, 2, NULL, NULL, 'New Hampshire', '', '', 4, NULL, NULL),
(34, 2, NULL, NULL, 'New Jersey', '', '', 14, NULL, NULL),
(35, 2, NULL, NULL, 'New Mexico', '', '', 5, NULL, NULL),
(36, 2, NULL, NULL, 'New York', '', '', 29, NULL, NULL),
(37, 2, NULL, NULL, 'North Carolina', '', '', 15, NULL, NULL),
(38, 2, NULL, NULL, 'North Dakota', '', '', 3, NULL, NULL),
(39, 2, NULL, NULL, 'Ohio', '', '', 18, NULL, NULL),
(40, 2, NULL, NULL, 'Oklahoma', '', '', 7, NULL, NULL),
(41, 2, NULL, NULL, 'Oregon', '', '', 7, NULL, NULL),
(42, 2, NULL, NULL, 'Pennsylvania', '', '', 20, NULL, NULL),
(43, 2, NULL, NULL, 'Rhode Island', '', '', 4, NULL, NULL),
(44, 2, NULL, NULL, 'South Carolina', '', '', 9, NULL, NULL),
(45, 2, NULL, NULL, 'South Dakota', '', '', 3, NULL, NULL),
(46, 2, NULL, NULL, 'Tennessee', '', '', 11, NULL, NULL),
(47, 2, NULL, NULL, 'Texas', '', '', 38, NULL, NULL),
(48, 2, NULL, NULL, 'Utah', '', '', 6, NULL, NULL),
(49, 2, NULL, NULL, 'Vermont', '', '', 3, NULL, NULL),
(50, 2, NULL, NULL, 'Virginia', '', '', 13, NULL, NULL),
(51, 2, NULL, NULL, 'Washington', '', '', 12, NULL, NULL),
(52, 2, NULL, NULL, 'West Virginia', '', '', 5, NULL, NULL),
(53, 2, NULL, NULL, 'Wisconsin', '', '', 10, NULL, NULL),
(54, 2, NULL, NULL, 'Wyoming', '', '', 3, NULL, NULL),
(57, 3, NULL, NULL, 'Black', '', '', 0, NULL, NULL),
(58, 3, 31, NULL, 'Blue', '', '', 0, NULL, NULL),
(59, 3, NULL, NULL, 'Green', '', '', 0, NULL, NULL),
(60, 3, NULL, NULL, 'Orange', '', '', 0, NULL, NULL),
(61, 3, NULL, NULL, 'Purple', '', '', 0, NULL, NULL),
(62, 3, 32, NULL, 'Red', '', '', 0, NULL, NULL),
(63, 3, NULL, NULL, 'White', '', '', 0, NULL, NULL),
(64, 3, NULL, NULL, 'Yellow', '', '', 0, NULL, NULL),
(65, 1, 33, NULL, 'Gary Johnson', 'Gary', 'Johnson', 0, NULL, NULL),
(66, 4, 1, NULL, 'China', '', '', 0, NULL, NULL),
(67, 4, 2, NULL, 'United States', '', '', 0, NULL, NULL),
(68, 4, 3, NULL, 'India', '', '', 0, NULL, NULL),
(69, 4, 4, NULL, 'Brazil', '', '', 0, NULL, NULL),
(70, 4, 5, NULL, 'Indonesia', '', '', 0, NULL, NULL),
(71, 4, 6, NULL, 'Japan', '', '', 0, NULL, NULL),
(72, 4, 7, NULL, 'Russia', '', '', 0, NULL, NULL),
(73, 4, 8, NULL, 'Germany', '', '', 0, NULL, NULL),
(74, 4, 9, NULL, 'Mexico', '', '', 0, NULL, NULL),
(75, 4, 10, NULL, 'Nigeria', '', '', 0, NULL, NULL),
(76, 4, 11, NULL, 'France', '', '', 0, NULL, NULL),
(77, 4, 12, NULL, 'United Kingdom', '', '', 0, NULL, NULL),
(78, 4, 13, NULL, 'Pakistan', '', '', 0, NULL, NULL),
(79, 4, 14, NULL, 'Italy', '', '', 0, NULL, NULL),
(80, 4, 15, NULL, 'Turkey', '', '', 0, NULL, NULL),
(81, 4, 16, NULL, 'Iran', '', '', 0, NULL, NULL),
(82, 1, 36, NULL, 'Ben Carson', 'Ben', 'Carson', 0, NULL, NULL),
(83, 1, 37, NULL, 'Chris Christie', 'Chris', 'Christie', 0, NULL, NULL),
(84, 1, 38, NULL, 'Elizabeth Warren', 'Elizabeth', 'Warren', 0, NULL, NULL),
(85, 1, 39, NULL, 'Jeb Bush', 'Jeb', 'Bush', 0, NULL, NULL),
(86, 1, 40, NULL, 'Jill Stein', 'Jill', 'Stein', 0, NULL, NULL),
(87, 1, 21, NULL, 'Joe Biden', 'Joe', 'Biden', 0, NULL, NULL),
(88, 1, 20, NULL, 'John Kasich', 'John', 'Kasich', 0, NULL, NULL),
(89, 1, 41, NULL, 'Marco Rubio', 'Marco', 'Rubio', 0, NULL, NULL),
(90, 1, 42, NULL, 'Michael Bloomberg', 'Michael', 'Bloomberg', 0, NULL, NULL),
(91, 1, 43, NULL, 'Mike Huckabee', 'Mike', 'Huckabee', 0, NULL, NULL),
(92, 1, 22, NULL, 'Mitt Romney', 'Mitt', 'Romney', 0, NULL, NULL),
(93, 1, 23, NULL, 'Paul Ryan', 'Paul', 'Ryan', 0, NULL, NULL),
(94, 1, 44, NULL, 'Rand Paul', 'Rand', 'Paul', 0, NULL, NULL),
(95, 1, 45, NULL, 'Sarah Palin', 'Sarah', 'Palin', 0, NULL, NULL),
(96, 1, 46, NULL, 'Scott Walker', 'Scott', 'Walker', 0, NULL, NULL),
(97, 1, 24, NULL, 'Ted Cruz', 'Ted', 'Cruz', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `entity_types`
--

CREATE TABLE `entity_types` (
  `entity_type_id` int(11) NOT NULL,
  `entity_name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `entity_types`
--

INSERT INTO `entity_types` (`entity_type_id`, `entity_name`) VALUES
(1, '2016 presidential candidates'),
(2, 'states in America'),
(3, 'empirecoin teams'),
(4, 'nations in the world'),
(5, 'general entity');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `sport_entity_id` int(11) DEFAULT NULL,
  `league_entity_id` int(11) DEFAULT NULL,
  `event_index` int(11) DEFAULT NULL,
  `next_event_index` int(11) DEFAULT NULL,
  `event_type_id` int(11) NOT NULL,
  `event_starting_block` int(11) DEFAULT '0',
  `event_final_block` int(11) DEFAULT '0',
  `event_starting_time` datetime DEFAULT NULL,
  `event_final_time` datetime DEFAULT NULL,
  `event_payout_block` int(11) DEFAULT NULL,
  `event_outcome_block` int(11) DEFAULT NULL,
  `payout_rule` enum('binary','linear','') NOT NULL DEFAULT 'binary',
  `payout_rate` float DEFAULT '1',
  `track_max_price` float DEFAULT NULL,
  `track_min_price` float DEFAULT NULL,
  `track_payout_price` float DEFAULT NULL,
  `track_entity_id` int(11) DEFAULT NULL,
  `track_name_short` varchar(50) DEFAULT NULL,
  `event_payout_time` datetime DEFAULT NULL,
  `outcome_index` int(11) DEFAULT NULL,
  `option_block_rule` varchar(100) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `option_name` varchar(255) DEFAULT NULL,
  `option_name_plural` varchar(255) DEFAULT NULL,
  `num_options` int(11) DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `completion_datetime` datetime DEFAULT NULL,
  `option_max_width` int(11) NOT NULL DEFAULT '0',
  `display_mode` enum('default','slim') NOT NULL DEFAULT 'slim',
  `sum_score` bigint(20) DEFAULT NULL,
  `destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `sum_votes` bigint(20) NOT NULL DEFAULT '0',
  `effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `sum_unconfirmed_score` bigint(20) NOT NULL DEFAULT '0',
  `sum_unconfirmed_votes` bigint(20) NOT NULL DEFAULT '0',
  `sum_unconfirmed_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `sum_unconfirmed_effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `winning_option_id` int(20) DEFAULT NULL,
  `winning_votes` bigint(20) NOT NULL DEFAULT '0',
  `winning_effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `payout_transaction_id` int(20) DEFAULT NULL,
  `external_identifier` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

CREATE TABLE `event_types` (
  `event_type_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `primary_entity_id` int(11) DEFAULT NULL,
  `secondary_entity_id` int(11) DEFAULT NULL,
  `option_group_id` int(11) DEFAULT NULL,
  `url_identifier` varchar(100) DEFAULT NULL,
  `event_winning_rule` enum('max_below_cap','game_definition') NOT NULL DEFAULT 'max_below_cap',
  `vote_effectiveness_function` enum('constant','linear_decrease') NOT NULL DEFAULT 'constant',
  `effectiveness_param1` decimal(12,8) DEFAULT NULL,
  `block_repetition_length` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_description` text NOT NULL,
  `num_voting_options` int(10) NOT NULL DEFAULT '0',
  `max_voting_fraction` decimal(3,2) NOT NULL DEFAULT '0.25',
  `default_option_max_width` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `external_addresses`
--

CREATE TABLE `external_addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `address` varchar(100) DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `featured_strategies`
--

CREATE TABLE `featured_strategies` (
  `featured_strategy_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `reference_account_id` int(11) DEFAULT NULL,
  `hit_url` tinyint(1) NOT NULL DEFAULT '0',
  `reference_starting_block` int(11) DEFAULT NULL,
  `strategy_name` varchar(255) DEFAULT NULL,
  `base_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `blockchain_id` int(11) DEFAULT NULL,
  `game_type_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `invoice_address_id` int(11) DEFAULT NULL,
  `logo_image_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `definitive_game_peer_id` int(11) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `cached_definition_hash` varchar(64) DEFAULT NULL,
  `defined_cached_definition_hash` varchar(64) DEFAULT NULL,
  `cached_definition_time` int(20) DEFAULT NULL,
  `creator_game_index` int(11) DEFAULT NULL,
  `game_series_index` int(11) DEFAULT NULL,
  `event_rule` enum('entity_type_option_group','single_event_series','all_pairs','game_definition') DEFAULT NULL,
  `event_winning_rule` varchar(100) DEFAULT NULL,
  `event_entity_type_id` int(11) DEFAULT NULL,
  `option_group_id` int(11) DEFAULT NULL,
  `protocol_version` float DEFAULT NULL,
  `events_per_round` int(11) NOT NULL DEFAULT '1',
  `event_type_name` varchar(50) NOT NULL DEFAULT '',
  `event_type_name_plural` varchar(50) DEFAULT NULL,
  `url_identifier` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_description` text NOT NULL,
  `game_winning_rule` enum('none','event_points') NOT NULL DEFAULT 'none',
  `game_winning_field` varchar(50) NOT NULL DEFAULT '',
  `game_winning_inflation` decimal(12,8) NOT NULL DEFAULT '0.00000000',
  `winning_entity_id` int(11) DEFAULT NULL,
  `game_winning_transaction_id` int(11) DEFAULT NULL,
  `game_status` enum('editable','published','running','completed','deleted') NOT NULL DEFAULT 'editable',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `featured_score` float NOT NULL DEFAULT '0',
  `public_players` tinyint(1) NOT NULL DEFAULT '0',
  `hide_module` tinyint(1) DEFAULT '0',
  `inflation` enum('linear','exponential','fixed_exponential') NOT NULL DEFAULT 'linear',
  `exponential_inflation_rate` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `exponential_inflation_minershare` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `pos_reward` bigint(20) NOT NULL DEFAULT '0',
  `pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `round_length` int(10) NOT NULL DEFAULT '10',
  `maturity` int(10) NOT NULL DEFAULT '0',
  `payout_weight` enum('coin','coin_block','coin_round') NOT NULL DEFAULT 'coin_block',
  `final_round` int(11) DEFAULT NULL,
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `initial_coins` bigint(20) NOT NULL DEFAULT '0',
  `coin_name` varchar(100) NOT NULL DEFAULT 'coin',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT 'coins',
  `coin_abbreviation` varchar(10) NOT NULL DEFAULT '',
  `decimal_places` int(11) NOT NULL DEFAULT '8',
  `finite_events` tinyint(1) DEFAULT '1',
  `buyin_policy` enum('unlimited','per_user_cap','game_cap','game_and_user_cap','none','for_sale') NOT NULL DEFAULT 'none',
  `per_user_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `game_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `faucet_policy` enum('on','off') NOT NULL DEFAULT 'on',
  `sellout_policy` enum('on','off') NOT NULL DEFAULT 'on',
  `sellout_confirmations` int(11) DEFAULT NULL,
  `escrow_address` varchar(100) DEFAULT NULL,
  `genesis_tx_hash` varchar(100) DEFAULT NULL,
  `genesis_amount` bigint(20) DEFAULT NULL,
  `game_starting_block` int(11) DEFAULT NULL,
  `start_condition` enum('fixed_block','fixed_time','players_joined') NOT NULL DEFAULT 'fixed_block',
  `start_condition_players` int(11) DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `start_time` int(20) DEFAULT NULL,
  `completion_datetime` datetime DEFAULT NULL,
  `payout_reminder_datetime` datetime DEFAULT NULL,
  `payout_complete` tinyint(1) NOT NULL DEFAULT '0',
  `payout_tx_hash` varchar(255) NOT NULL DEFAULT '',
  `giveaway_status` enum('public_free','invite_free','invite_pay','public_pay') NOT NULL DEFAULT 'public_free',
  `giveaway_amount` bigint(16) NOT NULL DEFAULT '0',
  `public_unclaimed_invitations` tinyint(1) NOT NULL DEFAULT '0',
  `invite_cost` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `invite_currency` int(11) DEFAULT NULL,
  `invitation_link` varchar(200) NOT NULL DEFAULT '',
  `sync_coind_by_cron` tinyint(1) NOT NULL DEFAULT '0',
  `events_until_block` int(11) DEFAULT NULL,
  `ensure_events_future_rounds` int(11) NOT NULL DEFAULT '0',
  `loaded_until_block` int(11) DEFAULT NULL,
  `coins_in_existence` bigint(20) NOT NULL DEFAULT '0',
  `cached_pending_bets` bigint(20) DEFAULT NULL,
  `cached_vote_supply` bigint(20) DEFAULT NULL,
  `min_option_index` int(11) DEFAULT NULL,
  `max_option_index` int(11) DEFAULT NULL,
  `min_payout_rate` float DEFAULT NULL,
  `max_payout_rate` float DEFAULT NULL,
  `send_round_notifications` tinyint(1) DEFAULT '1',
  `default_payout_rule` enum('binary','linear','') NOT NULL DEFAULT 'binary',
  `default_payout_rate` float DEFAULT '1',
  `default_vote_effectiveness_function` enum('constant','linear_decrease') DEFAULT 'constant',
  `default_effectiveness_param1` decimal(12,8) DEFAULT NULL,
  `default_max_voting_fraction` decimal(9,8) NOT NULL DEFAULT '1.00000000',
  `default_option_max_width` int(11) NOT NULL DEFAULT '200',
  `default_payout_block_delay` int(11) DEFAULT NULL,
  `default_betting_mode` enum('principal','inflationary') NOT NULL DEFAULT 'inflationary',
  `view_mode` enum('default','simple') NOT NULL DEFAULT 'default',
  `default_display_currency_id` int(11) DEFAULT '1',
  `default_buyin_currency_id` int(11) NOT NULL DEFAULT '6',
  `default_contract_parts` bigint(20) DEFAULT '100000000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_blocks`
--

CREATE TABLE `game_blocks` (
  `game_block_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `block_id` int(11) DEFAULT NULL,
  `locally_saved` tinyint(1) NOT NULL DEFAULT '0',
  `num_transactions` int(11) DEFAULT NULL,
  `num_ios_in` int(11) DEFAULT NULL,
  `num_ios_out` int(11) DEFAULT NULL,
  `sum_coins_in` bigint(20) DEFAULT NULL,
  `sum_coins_out` bigint(20) DEFAULT NULL,
  `max_game_io_index` int(11) DEFAULT NULL,
  `load_time` float NOT NULL DEFAULT '0',
  `time_created` int(11) DEFAULT NULL,
  `time_loaded` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_defined_escrow_amounts`
--

CREATE TABLE `game_defined_escrow_amounts` (
  `escrow_amount_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `amount` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_defined_events`
--

CREATE TABLE `game_defined_events` (
  `game_defined_event_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `sport_entity_id` int(11) DEFAULT NULL,
  `league_entity_id` int(11) DEFAULT NULL,
  `event_index` int(11) DEFAULT NULL,
  `next_event_index` int(11) DEFAULT NULL,
  `event_starting_block` int(11) DEFAULT NULL,
  `event_final_block` int(11) DEFAULT NULL,
  `event_starting_time` datetime DEFAULT NULL,
  `event_final_time` datetime DEFAULT NULL,
  `event_payout_block` int(11) DEFAULT NULL,
  `event_outcome_block` int(11) DEFAULT NULL,
  `payout_rule` enum('binary','linear','') NOT NULL DEFAULT 'binary',
  `payout_rate` float DEFAULT '1',
  `track_max_price` float DEFAULT NULL,
  `track_min_price` float DEFAULT NULL,
  `track_payout_price` float DEFAULT NULL,
  `track_entity_id` int(11) DEFAULT NULL,
  `track_name_short` varchar(50) DEFAULT NULL,
  `event_payout_time` datetime DEFAULT NULL,
  `option_block_rule` varchar(100) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `option_name` varchar(255) DEFAULT NULL,
  `option_name_plural` varchar(255) DEFAULT NULL,
  `outcome_index` int(11) DEFAULT NULL,
  `external_identifier` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_defined_options`
--

CREATE TABLE `game_defined_options` (
  `game_defined_option_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `event_index` int(11) DEFAULT NULL,
  `option_index` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `target_probability` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_definitions`
--

CREATE TABLE `game_definitions` (
  `game_definition_id` int(11) NOT NULL,
  `definition_hash` varchar(100) DEFAULT NULL,
  `definition` longtext
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_escrow_amounts`
--

CREATE TABLE `game_escrow_amounts` (
  `escrow_account_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `amount` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_invitations`
--

CREATE TABLE `game_invitations` (
  `invitation_id` int(20) NOT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `giveaway_id` int(11) DEFAULT NULL,
  `inviter_id` int(20) DEFAULT NULL,
  `invitation_key` varchar(100) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `sent_email_id` int(20) DEFAULT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_ip` varchar(50) NOT NULL DEFAULT '',
  `used_user_id` int(20) DEFAULT NULL,
  `used_time` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_peers`
--

CREATE TABLE `game_peers` (
  `game_peer_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `peer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_sellouts`
--

CREATE TABLE `game_sellouts` (
  `sellout_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `in_block_id` int(11) DEFAULT NULL,
  `out_block_id` int(11) DEFAULT NULL,
  `in_tx_hash` varchar(64) DEFAULT NULL,
  `out_tx_hash` varchar(64) DEFAULT NULL,
  `color_amount_in` bigint(20) NOT NULL DEFAULT '0',
  `exchange_rate` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `amount_in` bigint(20) NOT NULL DEFAULT '0',
  `amount_out` bigint(20) NOT NULL DEFAULT '0',
  `out_amounts` varchar(255) NOT NULL DEFAULT '',
  `fee_amount` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_types`
--

CREATE TABLE `game_types` (
  `game_type_id` int(11) NOT NULL,
  `event_rule` enum('entity_type_option_group','single_event_series') DEFAULT NULL,
  `event_entity_type_id` int(11) DEFAULT NULL,
  `option_group_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `event_type_name` varchar(50) NOT NULL DEFAULT '',
  `event_type_name_plural` varchar(50) DEFAULT NULL,
  `events_per_round` int(11) NOT NULL DEFAULT '0',
  `target_open_games` int(11) NOT NULL DEFAULT '0',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `game_winning_rule` enum('none','event_points') NOT NULL DEFAULT 'none',
  `game_winning_field` varchar(50) NOT NULL DEFAULT '',
  `url_identifier` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL DEFAULT '',
  `short_description` text NOT NULL,
  `inflation` enum('linear','exponential','fixed_exponential') NOT NULL DEFAULT 'linear',
  `exponential_inflation_rate` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `exponential_inflation_minershare` decimal(9,8) NOT NULL DEFAULT '0.00000000',
  `pos_reward` bigint(20) NOT NULL DEFAULT '0',
  `pow_reward` bigint(20) NOT NULL DEFAULT '0',
  `round_length` int(10) NOT NULL DEFAULT '10',
  `seconds_per_block` int(11) NOT NULL DEFAULT '0',
  `maturity` int(10) NOT NULL DEFAULT '8',
  `payout_weight` enum('coin','coin_block','coin_round') NOT NULL DEFAULT 'coin_block',
  `game_starting_block` int(11) DEFAULT NULL,
  `final_round` int(11) DEFAULT NULL,
  `coin_name` varchar(100) NOT NULL DEFAULT 'coin',
  `coin_name_plural` varchar(100) NOT NULL DEFAULT 'coins',
  `coin_abbreviation` varchar(10) NOT NULL DEFAULT '',
  `start_condition` enum('fixed_time','players_joined') NOT NULL DEFAULT 'players_joined',
  `start_condition_players` int(11) DEFAULT NULL,
  `block_timing` enum('realistic','user_controlled') NOT NULL DEFAULT 'realistic',
  `buyin_policy` enum('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none',
  `per_user_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `game_buyin_cap` decimal(16,8) NOT NULL DEFAULT '0.00000000',
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
  `default_game_winning_inflation` decimal(12,8) NOT NULL DEFAULT '0.00000000',
  `default_option_max_width` int(11) NOT NULL DEFAULT '0',
  `default_logo_image_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `game_types`
--

INSERT INTO `game_types` (`game_type_id`, `event_rule`, `event_entity_type_id`, `option_group_id`, `currency_id`, `event_type_name`, `event_type_name_plural`, `events_per_round`, `target_open_games`, `featured`, `game_winning_rule`, `game_winning_field`, `url_identifier`, `name`, `short_description`, `inflation`, `exponential_inflation_rate`, `exponential_inflation_minershare`, `pos_reward`, `pow_reward`, `round_length`, `seconds_per_block`, `maturity`, `payout_weight`, `game_starting_block`, `final_round`, `coin_name`, `coin_name_plural`, `coin_abbreviation`, `start_condition`, `start_condition_players`, `block_timing`, `buyin_policy`, `per_user_buyin_cap`, `game_buyin_cap`, `giveaway_status`, `giveaway_amount`, `public_unclaimed_invitations`, `invite_cost`, `invite_currency`, `invitation_link`, `always_generate_coins`, `min_unallocated_addresses`, `sync_coind_by_cron`, `send_round_notifications`, `default_vote_effectiveness_function`, `default_max_voting_fraction`, `default_game_winning_inflation`, `default_option_max_width`, `default_logo_image_id`) VALUES
(1, 'entity_type_option_group', 2, 1, NULL, 'election', NULL, 2, 1, 1, 'event_points', 'electoral_votes', 'mock-election-2016-day-', 'Mock Election 2016, Day ', 'Win empirecoins by predicting the winner in each of the 50 states. In this game, two state elections are held simultaneously every hour.  The candidate with the most electoral votes wins the game. This game repeats daily. Join now for free and receive 500 empirecoins.', 'exponential', '0.10000000', '0.00500000', 0, 0, 30, 30, 0, 'coin_block', NULL, 25, 'empirecoin', 'empirecoins', 'EMP', 'players_joined', 12, 'realistic', 'unlimited', '0.00000000', '0.00000000', 'public_free', 50000000000, 0, '0.00000000', 1, '', 0, 2, 0, 1, 'linear_decrease', '0.70000000', '1.00000000', 120, NULL),
(2, 'single_event_series', NULL, 5, NULL, 'EmpireCoin Classic', NULL, 1, 1, 1, 'none', '', 'empirecoin-classic', 'EmpireCoin Classic', '', 'linear', '0.00000000', '0.00000000', 75000000000, 2500000000, 10, 20, 0, 'coin', NULL, NULL, 'empirecoin', 'empirecoins', 'EMP', 'players_joined', 2, 'realistic', 'unlimited', '0.00000000', '0.00000000', 'public_free', 0, 0, '0.00000000', NULL, '', 0, 2, 1, 1, 'constant', '0.25000000', '0.00000000', 200, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `image_id` int(11) NOT NULL,
  `access_key` varchar(50) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  `px_from_left` int(11) DEFAULT NULL,
  `px_from_top` int(11) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `image_identifier` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `images`
--

INSERT INTO `images` (`image_id`, `access_key`, `extension`, `px_from_left`, `px_from_top`, `width`, `height`, `image_identifier`) VALUES
(1, '', 'jpg', NULL, NULL, NULL, NULL, '92f7182599065abae3d6ce50d44dd844b659b05f4f57c76350f0ab79e84cfd08'),
(2, '', 'jpg', NULL, NULL, NULL, NULL, '7635d37492b2e09bafdec1f2b20a3d7c742a25cbc8dcb7e2d51160e91296ca1a'),
(3, '', 'jpg', NULL, NULL, NULL, NULL, '17f9fbfe1de7df896199cb8fdb49865de2b332963a9515eefe03b421aa16d075'),
(4, '', 'jpg', NULL, NULL, NULL, NULL, 'aeb0ef73bb5d59b88a7e821285c5d4d020e7706e32fcb87101b5076d4afce18d'),
(5, '', 'jpg', NULL, NULL, NULL, NULL, 'ac1d4113f721c549a9e72237cdbb8dfa5145644f936c001f4a65c4cb381f1c8a'),
(6, '', 'jpg', NULL, NULL, NULL, NULL, '5e6b1d60315d05bec8a5e3c22cbe4ebb7f86c4479034dfcc936ce6f7cf3fa05b'),
(7, '', 'jpg', NULL, NULL, NULL, NULL, 'e2ddb40303992da5bd31c659ba03ffd65a6f80b044f68764729a5415f9e7d7ee'),
(8, '', 'jpg', NULL, NULL, NULL, NULL, 'acca0ccea40dba06c5ebc09e596a2a92a5f991e93975fd516e920d049957b69c'),
(9, '', 'jpg', NULL, NULL, NULL, NULL, '62e36f1a95acd4326b93eccacf41892eee5c9d1d163d22bbdc7b5cf36bf385eb'),
(10, '', 'jpg', NULL, NULL, NULL, NULL, 'ab4d9fc85add936b1dbf0c4df11404d533de3fca7b81129b60a9205639d2c6fb'),
(11, '', 'jpg', NULL, NULL, NULL, NULL, '2d069a9528453c97da3e3b7da6c6eca06d2e5f6b8d33c204b2bbd28829eac1eb'),
(12, '', 'jpg', NULL, NULL, NULL, NULL, 'bee917f453a475442bbc54561da95b50b9e9befb3c54eb2991741a50f40ca81c'),
(13, '', 'jpg', NULL, NULL, NULL, NULL, 'f1a65324a9d092e88c4b5127e005407a4bd4b0480d6b6a71b44c3e247008ed6f'),
(14, '', 'jpg', NULL, NULL, NULL, NULL, '10cacf656b06797c2d7aea18e8e2190d478d020886e4118aa7be0a0c1582bfd3'),
(15, '', 'jpg', NULL, NULL, NULL, NULL, 'b0910795bf63b71ac3a8ba60e6077613b7bb88ecb3f2af21c4e7b99f5e12d664'),
(16, '', 'jpg', NULL, NULL, NULL, NULL, '31faf33cb526763f8ff387d876317c7b56a81122a94587fcc1221aaf5bf5b8c2'),
(17, '', 'jpg', NULL, NULL, NULL, NULL, 'd62842cbb7cd2c2ad47405457c87bd18806fc28e48d6bdad570f6b5f06ffa2d4'),
(18, '', 'jpg', NULL, NULL, NULL, NULL, 'efdba7b533d2bbb8b40eacf38d82f46b2b92a62ad6f9d0755fb17a6513efab59'),
(19, '', 'jpg', NULL, NULL, NULL, NULL, '98acebb319b2040a3507079147d16a695b551f71929241cb65c03d31966b21b2'),
(20, '', 'jpg', NULL, NULL, NULL, NULL, '74bb56d1df60fd3cdfdd2e1af4c6be9c3014bd63ea01c9f82f2067590c932975'),
(21, '', 'jpg', NULL, NULL, NULL, NULL, 'f7435c14a7a9308c55036d9b8bac94e30bde44453310ce7d062403e171b43d07'),
(22, '', 'jpg', NULL, NULL, NULL, NULL, 'a1ebac4ad269aa75c05c83830d420be988c2d34cc97390b0b46936ca09046758'),
(23, '', 'jpg', NULL, NULL, NULL, NULL, 'f35916e3d00a3edd04a2c736dee418c0289475858ba448843c79b6c649d98a03'),
(24, '', 'jpg', NULL, NULL, NULL, NULL, 'e29c020001e0d33c1409bde277a3a2574fa1e6bd02dc1393b446bea3a63d16c7'),
(25, '', 'png', NULL, NULL, NULL, NULL, '98d0d0d79917d03e2c01fcacf150bac1117f6ca31f5c9d39f4bbcb0ad7ed5066'),
(26, '', 'png', NULL, NULL, NULL, NULL, 'e27c2da61f8ded03d02cd3b3f22816b897c5742ce2cae2a540b15d4becabeeff'),
(27, '', 'png', NULL, NULL, NULL, NULL, '45410d05c1df1b0c474254446d15ca56bb6d3ed00a51a42753ac90bebecbfe11'),
(28, '', 'png', NULL, NULL, NULL, NULL, '36d85e26a0cc4d77a83864f00682754318e08e87561d78580224fc822f4ddab5'),
(29, '', 'png', NULL, NULL, NULL, NULL, 'e1de8e32310715836f6774f2714595f4df24aedef689a288f837ce2aaf68eb85'),
(30, '', 'jpg', NULL, NULL, NULL, NULL, 'bd5d7fa5a5a72bd165a0f97ad4a17a220a22141e1bc48d57902938c3761c4cee'),
(31, '', 'png', NULL, NULL, NULL, NULL, '9fc2f7124d524b87d8bf47e6a110ef984caafc5d885c640c5a78f654ec70e3a3'),
(32, '', 'png', NULL, NULL, NULL, NULL, '1d1e2a16e37fb122085b2a4292677fa0dc10c7d8c2a04722b2801bb4dfd636fb'),
(34, '', 'png', NULL, NULL, NULL, NULL, 'f8d471c33242aeb559b16ca24ca7a57cdfc3859f659e08c5097e136d15ae8d28'),
(35, '', 'png', NULL, NULL, NULL, NULL, 'ce271869e6f41179a5db12e4f978dae4bd98c9d88a5d4434511a35ff6bdf6413'),
(36, '', 'jpg', NULL, NULL, NULL, NULL, '26507f733bd854b4c4824e61abdc7c4483055762b6ad561bb1f78751440a3ca7'),
(37, '', 'jpg', NULL, NULL, NULL, NULL, '30a598ad048556e5cb07a6e432cb35f482cc0388c13c43ccde5e193541adf38e'),
(38, '', 'jpg', NULL, NULL, NULL, NULL, '048800bbfbc5cda7ffdffd7bd49828d4e9e52cd0895f2bf1aaed42cbdc7e2d73'),
(39, '', 'jpg', NULL, NULL, NULL, NULL, 'de821456b108c020dcb11ec7160108bbd583245642267e2aec5c6a4831df2fed'),
(40, '', 'jpg', NULL, NULL, NULL, NULL, '35267663f551a346fa67edf1426108faf0bfcce9b16a952a6fd148c00084e25c'),
(41, '', 'jpg', NULL, NULL, NULL, NULL, '6e872978fa26140400ba8778a88932159b2c4370d965bd44bd67fa1654c8319b'),
(42, '', 'jpg', NULL, NULL, NULL, NULL, 'b71002a06ce3ebf9be4bbdf995850473302debbe35a8f1df0d16e3eeb2d59fc6'),
(43, '', 'jpg', NULL, NULL, NULL, NULL, '5c44533f46ce65540915c9199eb9201bb75b15a85e7c4933058831a69c7a0d93'),
(44, '', 'jpg', NULL, NULL, NULL, NULL, '2e2a31fe7a912a8c644fd010bcc09e2c259a3b9a32c127d8bae57d2bc7eaa45a'),
(45, '', 'jpg', NULL, NULL, NULL, NULL, '3580c46acc4045b8e7346d09a1b7fa070cde980dadacd413145659c0ce989b4e'),
(46, '', 'jpg', NULL, NULL, NULL, NULL, '8f84cf0e2c413065fdefab873e1de54edbfdbb824b8709068b3cafcb98954126'),
(47, '', 'png', NULL, NULL, NULL, NULL, 'db0314671fc1b28a27d840184c68d108bf10902fd96f26f57461c4e94d62bb19'),
(48, '', 'png', NULL, NULL, NULL, NULL, '96a7fd766c27eff86f482299906df6d95ce17820e7533303b36dfd70e17706aa'),
(49, '', 'png', NULL, NULL, NULL, NULL, 'f0c73966cac3641c10c1ee0eb60c749e2d775d5a4e6c8d7e12de20a45d9cbe16'),
(50, '', 'png', NULL, NULL, NULL, NULL, 'bf2a556073fb854d38ed2a40dcfde912e5ba96dd296761c7030674850db3debe'),
(51, '', 'png', NULL, NULL, NULL, NULL, 'a7b8e4590b479a67512ce3223ecb5788568508d93663a090b92853de58f14776'),
(52, '', 'png', NULL, NULL, NULL, NULL, '81a60cf0c91c065e613a7e19d738b0bede4f7b9109801322792116b330f51469'),
(53, '', 'png', NULL, NULL, NULL, NULL, '8d6e976bce511a872a6741aab09364a343fac8dfb0040859c29cdc1c3fd02f52'),
(54, '', 'png', NULL, NULL, NULL, NULL, 'e2e26ab5f90b11e7bee078ab22e40732791cdcf18d5fa4a1aeb9554470a93d0b'),
(55, '', 'png', NULL, NULL, NULL, NULL, 'cbc5de4829b3e6f2b24b983f2a0c62a4ec664926684ec0a4939ff80392e72e27'),
(56, '', 'png', NULL, NULL, NULL, NULL, 'd7f3806f6b73685cdbc7478dac6b17e5a23e9998306b6efca9c34695250f3bf4'),
(57, '', 'png', NULL, NULL, NULL, NULL, '1db894cdfaa3bc5fb45e0819f38789e3a60862c183eb22c9563e9f68dccbb83d'),
(58, '', 'png', NULL, NULL, NULL, NULL, 'd7c885d320b38f0c55f36cc49ca9cb337581c80c9fbfa9e0ba64df9972430316'),
(59, '', 'png', NULL, NULL, NULL, NULL, '42e7db929c3f420979c94cbf5ab2000029dbc7cc71a8651bbd2cc2e13891729f'),
(60, '', 'png', NULL, NULL, NULL, NULL, '3d30f0bfa4d9edf994787b2a7c97b20cc0132c78d9d246121105e4a258573924'),
(61, '', 'png', NULL, NULL, NULL, NULL, '4a555e68b24fb4d552afcfd339cd53d3eb66c10c9ded6bbd22ae18bcf11fe31e'),
(62, '', 'png', NULL, NULL, NULL, NULL, 'ec0fae2bc5546979e2d8006b2cfedd5292556247fde6618f7af1197a8dd4e540'),
(63, '', 'png', NULL, NULL, NULL, NULL, 'e3406f6a71355556e104de529da6f48da6dc35cb03575bc887a8705125729372'),
(64, '', 'png', NULL, NULL, NULL, NULL, '15174c688144e4e43e8e2d2f5d5a059acc571be9758988bddd5f7678cfd140b2'),
(65, '', 'png', NULL, NULL, NULL, NULL, '0981c522ca531f6e1a3b306663f36c82a775461e0d365653d9b73638dc7df563'),
(66, '', 'png', NULL, NULL, NULL, NULL, '55e696c29f95ed83ddbcb2a82c45b288ffcf46c483b282c4777d3d7caf73835c'),
(67, '', 'png', NULL, NULL, NULL, NULL, '1b65297675f8d0a5b23868641f2288718f1bebc36fcb634c5b3c3f186afb18f3'),
(68, '', 'png', NULL, NULL, NULL, NULL, '1c25c88078a42bc43571ea5ed3a1cdee71de86db93f346d6ce4a0ae1a27683f9'),
(69, '', 'png', NULL, NULL, NULL, NULL, '0575a2c14d319c721743fd05638384a5ae58a64ec50c1493510854f0b5323261'),
(70, '', 'png', NULL, NULL, NULL, NULL, '0277d5dbd9dba06283ff2a410ad30fdb204ac385f8572e2c9e7cc6e7b2b600b4'),
(71, '', 'png', NULL, NULL, NULL, NULL, '06167df25e5b1538eeb16799586ec8a83363effe0124bfcca51fee3ae92cc4e7'),
(72, '', 'png', NULL, NULL, NULL, NULL, '653ef48ad63b2c5aaae43c42ebddd8366b2b9afc18737f4e1536207cfa6d2cca'),
(73, '', 'png', NULL, NULL, NULL, NULL, 'ccd75a0c38ee312007f9d8192470c6ea02fdffd33523bfa90ca9214c87498871'),
(74, '', 'png', NULL, NULL, NULL, NULL, '99d20c2523b98a01f3a1230988c14cff3af136b520b7eddf539c3e3d9bda0845'),
(75, '', 'png', NULL, NULL, NULL, NULL, 'edfa72ac1003b46cb6b34178aac7a29022318c60ae1ace528eb65a4c3b954b32'),
(76, '', 'png', NULL, NULL, NULL, NULL, 'e8b1f77a9468b068541f0d2a83b2b44216c2118a95cf299eb28317e933db42b9'),
(77, '', 'png', NULL, NULL, NULL, NULL, 'cccc42bf198f0aeee390a642bfb691a80343592bbf4e7a3e628b696a0b419659'),
(78, '', 'png', NULL, NULL, NULL, NULL, '419a065902183d9fb33bc9573c60bda9725941575218a5e32c7f6e88dc5dedf7'),
(79, '', 'png', NULL, NULL, NULL, NULL, '25bffbfee6ea6add1c96b01591b6ae9da1895b0b1145de66b1bdae293dc69494'),
(80, '', 'png', -606, -360, 1410, 1410, '9cf18cd6cc6efc8b037c03f4318de053ee787fb93acb4a3315b193be3401566c'),
(81, '', 'png', NULL, NULL, 480, 480, '2fec779bc8e8a44fab6011d879b78fa4e35bdd4a6f89cb9aae0e39b4f1b888b3');

-- --------------------------------------------------------

--
-- Table structure for table `log_messages`
--

CREATE TABLE `log_messages` (
  `message_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL DEFAULT '',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `mobile_payments`
--

CREATE TABLE `mobile_payments` (
  `payment_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `beyonic_request_id` int(11) DEFAULT NULL,
  `payment_type` enum('group_withdrawal','card_withdrawal','') NOT NULL DEFAULT '',
  `payment_status` enum('pending','complete','canceled','') NOT NULL DEFAULT '',
  `payment_key` varchar(100) DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `phone_number` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `time_created` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `module_id` int(11) NOT NULL,
  `module_name` varchar(255) DEFAULT NULL,
  `primary_game_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`module_id`, `module_name`, `primary_game_id`) VALUES
(1, 'CoinBattles', NULL),
(2, 'SingleElimination', NULL),
(3, 'ElectionSim', NULL),
(4, 'EmpirecoinClassic', NULL),
(5, 'StakemoneyShares', NULL),
(6, 'ImageTournament', NULL),
(7, 'CryptoDuels', NULL),
(8, 'DailyCryptoMarkets', NULL),
(9, 'eSports', NULL),
(10, 'VirtualStockMarket', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `subscriber_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(11) NOT NULL DEFAULT '0',
  `subscribed` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `option_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `membership_id` int(11) DEFAULT NULL,
  `image_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `vote_identifier` varchar(20) NOT NULL DEFAULT '',
  `option_index` int(11) DEFAULT NULL,
  `event_option_index` int(11) DEFAULT NULL,
  `target_probability` float DEFAULT NULL,
  `coin_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_block_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_coin_round_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `votes` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_votes` bigint(20) NOT NULL DEFAULT '0',
  `effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `unconfirmed_effective_destroy_score` bigint(20) NOT NULL DEFAULT '0',
  `option_block_score` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `option_blocks`
--

CREATE TABLE `option_blocks` (
  `option_block_id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `block_height` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `rand_chars` varchar(10) DEFAULT NULL,
  `rand_prob` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `option_groups`
--

CREATE TABLE `option_groups` (
  `group_id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL DEFAULT '',
  `option_name_plural` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `option_group_memberships`
--

CREATE TABLE `option_group_memberships` (
  `membership_id` int(20) NOT NULL,
  `option_group_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oracle_urls`
--

CREATE TABLE `oracle_urls` (
  `oracle_url_id` int(11) NOT NULL,
  `format_id` int(11) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `oracle_urls`
--

INSERT INTO `oracle_urls` (`oracle_url_id`, `format_id`, `url`) VALUES
(1, 1, 'http://api.fixer.io/latest?base=USD'),
(2, 2, 'https://api.bitcoinaverage.com/ticker/global/all'),
(3, 3, 'https://coinmarketcap.com/');

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
  `refer_url` varchar(255) NOT NULL DEFAULT ''
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
-- Table structure for table `peers`
--

CREATE TABLE `peers` (
  `peer_id` int(11) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `peer_identifier` varchar(100) DEFAULT NULL,
  `peer_name` varchar(100) DEFAULT NULL,
  `base_url` varchar(255) DEFAULT NULL,
  `time_created` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `peers`
--

INSERT INTO `peers` (`peer_id`, `visible`, `peer_identifier`, `peer_name`, `base_url`, `time_created`) VALUES
(1, 1, 'poly.cash', 'poly.cash', 'https://poly.cash', 1554050326);

-- --------------------------------------------------------

--
-- Table structure for table `redirect_urls`
--

CREATE TABLE `redirect_urls` (
  `redirect_url_id` int(20) NOT NULL,
  `redirect_key` varchar(24) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `redirect_urls`
--

INSERT INTO `redirect_urls` (`redirect_url_id`, `redirect_key`, `url`, `time_created`) VALUES
(1, 'kfN41nYBmtMnKKBEBHLum9BF', '/install.php?key=35neeke8XPpu', 1578254616);

-- --------------------------------------------------------

--
-- Table structure for table `site_constants`
--

CREATE TABLE `site_constants` (
  `constant_id` int(20) NOT NULL,
  `constant_name` varchar(100) NOT NULL DEFAULT '',
  `constant_value` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `site_constants`
--

INSERT INTO `site_constants` (`constant_id`, `constant_name`, `constant_value`) VALUES
(1, 'last_migration_id', '165');

-- --------------------------------------------------------

--
-- Table structure for table `strategy_round_allocations`
--

CREATE TABLE `strategy_round_allocations` (
  `allocation_id` int(20) NOT NULL,
  `strategy_id` int(20) DEFAULT NULL,
  `round_id` int(20) DEFAULT NULL,
  `option_id` int(20) DEFAULT NULL,
  `points` int(20) NOT NULL DEFAULT '0',
  `applied` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(20) NOT NULL,
  `blockchain_id` int(11) DEFAULT NULL,
  `block_id` int(20) DEFAULT NULL,
  `transaction_desc` enum('coinbase','giveaway','transaction','votebase','bet','betbase','') NOT NULL DEFAULT '',
  `votebase_event_id` int(11) DEFAULT NULL,
  `tx_hash` varchar(64) NOT NULL DEFAULT '',
  `tx_memo` varchar(255) NOT NULL DEFAULT '',
  `amount` bigint(20) NOT NULL DEFAULT '0',
  `fee_amount` bigint(20) NOT NULL DEFAULT '0',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `ref_block_id` bigint(20) DEFAULT NULL,
  `ref_coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `position_in_block` int(11) DEFAULT NULL,
  `num_inputs` int(11) DEFAULT NULL,
  `num_outputs` int(11) DEFAULT NULL,
  `has_all_inputs` tinyint(1) NOT NULL DEFAULT '0',
  `has_all_outputs` tinyint(1) NOT NULL DEFAULT '0',
  `load_time` float NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_game_ios`
--

CREATE TABLE `transaction_game_ios` (
  `game_io_id` int(11) NOT NULL,
  `io_id` int(11) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `parent_io_id` int(11) DEFAULT NULL,
  `payout_io_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `game_out_index` int(11) DEFAULT NULL,
  `game_io_index` int(11) DEFAULT NULL,
  `colored_amount` bigint(20) NOT NULL DEFAULT '0',
  `destroy_amount` bigint(20) NOT NULL DEFAULT '0',
  `contract_parts` bigint(20) DEFAULT NULL,
  `is_coinbase` tinyint(1) NOT NULL DEFAULT '0',
  `is_resolved` tinyint(4) NOT NULL DEFAULT '0',
  `resolved_before_spent` tinyint(1) DEFAULT NULL,
  `votes` bigint(20) DEFAULT NULL,
  `effective_destroy_amount` bigint(20) NOT NULL DEFAULT '0',
  `effectiveness_factor` decimal(9,8) DEFAULT NULL,
  `instantly_mature` tinyint(1) NOT NULL DEFAULT '0',
  `create_block_id` int(11) DEFAULT NULL,
  `ref_block_id` int(11) DEFAULT NULL,
  `ref_round_id` int(11) DEFAULT NULL,
  `ref_coin_blocks` bigint(20) DEFAULT NULL,
  `ref_coin_rounds` bigint(20) DEFAULT NULL,
  `coin_blocks_created` bigint(20) DEFAULT NULL,
  `coin_blocks_destroyed` bigint(20) DEFAULT NULL,
  `coin_rounds_created` bigint(20) DEFAULT NULL,
  `coin_rounds_destroyed` bigint(20) DEFAULT NULL,
  `create_round_id` int(11) DEFAULT NULL,
  `spend_round_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_ios`
--

CREATE TABLE `transaction_ios` (
  `io_id` int(20) NOT NULL,
  `blockchain_id` int(11) DEFAULT NULL,
  `address_id` int(20) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `option_index` int(11) DEFAULT NULL,
  `spend_status` enum('spent','unspent','unconfirmed') NOT NULL DEFAULT 'unconfirmed',
  `out_index` int(11) DEFAULT '0',
  `in_index` int(11) DEFAULT NULL,
  `is_destroy` tinyint(1) NOT NULL DEFAULT '0',
  `is_separator` tinyint(1) NOT NULL DEFAULT '0',
  `is_passthrough` tinyint(1) NOT NULL DEFAULT '0',
  `is_receiver` tinyint(1) NOT NULL DEFAULT '0',
  `create_transaction_id` int(20) DEFAULT NULL,
  `spend_transaction_id` int(20) DEFAULT NULL,
  `script_type` varchar(100) DEFAULT NULL,
  `amount` double DEFAULT '0',
  `coin_blocks_created` bigint(20) NOT NULL DEFAULT '0',
  `coin_blocks_destroyed` bigint(20) NOT NULL DEFAULT '0',
  `create_block_id` int(20) DEFAULT NULL,
  `spend_block_id` int(20) DEFAULT NULL,
  `spend_count` int(11) DEFAULT '0',
  `spend_transaction_ids` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(20) NOT NULL,
  `game_id` int(11) NOT NULL DEFAULT '1',
  `logged_in` tinyint(4) NOT NULL DEFAULT '0',
  `login_method` enum('password','email') COLLATE latin1_german2_ci NOT NULL DEFAULT 'password',
  `account_value` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `username` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `password` varchar(64) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `first_name` varchar(30) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `last_name` varchar(30) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `ip_address` varchar(40) COLLATE latin1_german2_ci DEFAULT NULL,
  `time_created` int(20) DEFAULT NULL,
  `verify_code` varchar(64) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `salt` varchar(16) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `verified` tinyint(1) NOT NULL DEFAULT '1',
  `notification_email` varchar(100) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `last_active` int(30) NOT NULL DEFAULT '0',
  `authorized_games` int(11) NOT NULL DEFAULT '0',
  `left_menu_open` tinyint(1) NOT NULL DEFAULT '1'
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
  `account_id` int(11) DEFAULT NULL,
  `current_invoice_id` int(11) DEFAULT NULL,
  `selected` tinyint(1) NOT NULL DEFAULT '0',
  `payment_required` tinyint(1) NOT NULL DEFAULT '0',
  `paid_invoice_id` int(11) DEFAULT NULL,
  `payout_address_id` int(11) DEFAULT NULL,
  `show_intro_message` tinyint(1) NOT NULL DEFAULT '0',
  `prompt_notification_preference` tinyint(1) NOT NULL DEFAULT '0',
  `notification_preference` enum('email','none') NOT NULL DEFAULT 'none',
  `api_access_code` varchar(50) DEFAULT NULL,
  `faucet_claims` int(11) DEFAULT '0',
  `event_index` int(11) NOT NULL DEFAULT '0',
  `betting_mode` enum('principal','inflationary') NOT NULL DEFAULT 'inflationary',
  `display_currency_id` int(11) DEFAULT NULL,
  `buyin_currency_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_login_links`
--

CREATE TABLE `user_login_links` (
  `login_link_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `access_key` varchar(32) DEFAULT NULL,
  `time_created` int(11) DEFAULT NULL,
  `time_clicked` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_messages`
--

CREATE TABLE `user_messages` (
  `message_id` int(20) NOT NULL,
  `game_id` int(20) DEFAULT NULL,
  `from_user_id` int(20) DEFAULT NULL,
  `to_user_id` int(20) DEFAULT NULL,
  `message` text NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0',
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
  `ip_address` varchar(30) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `synchronizer_token` varchar(32) COLLATE latin1_german2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_strategies`
--

CREATE TABLE `user_strategies` (
  `strategy_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `featured_strategy_id` int(11) DEFAULT NULL,
  `transaction_fee` decimal(16,10) NOT NULL DEFAULT '0.0001000000',
  `voting_strategy` enum('manual','by_rank','by_entity','by_plan','api','featured','hit_url','') NOT NULL DEFAULT 'manual',
  `aggregate_threshold` int(11) NOT NULL DEFAULT '0',
  `by_rank_ranks` varchar(100) NOT NULL DEFAULT '',
  `api_url` varchar(255) NOT NULL DEFAULT '',
  `min_votesum_pct` int(11) NOT NULL DEFAULT '0',
  `max_votesum_pct` int(11) NOT NULL DEFAULT '100',
  `min_coins_available` decimal(10,8) NOT NULL DEFAULT '0.00000000',
  `time_next_apply` int(11) DEFAULT NULL
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

-- --------------------------------------------------------

--
-- Table structure for table `user_strategy_entities`
--

CREATE TABLE `user_strategy_entities` (
  `strategy_option_id` int(11) NOT NULL,
  `strategy_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `pct_points` int(3) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `viewers`
--

CREATE TABLE `viewers` (
  `viewer_id` int(20) NOT NULL,
  `account_id` int(20) NOT NULL DEFAULT '0',
  `left_menu_open` tinyint(1) NOT NULL DEFAULT '1',
  `time_created` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `viewers`
--

INSERT INTO `viewers` (`viewer_id`, `account_id`, `left_menu_open`, `time_created`) VALUES
(1, 0, 1, 1470064899);

-- --------------------------------------------------------

--
-- Table structure for table `viewer_connections`
--

CREATE TABLE `viewer_connections` (
  `connection_id` int(20) NOT NULL,
  `type` enum('viewer2viewer','viewer2user') COLLATE latin1_german2_ci NOT NULL DEFAULT 'viewer2user',
  `from_id` int(20) NOT NULL DEFAULT '0',
  `to_id` int(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `viewer_identifiers`
--

CREATE TABLE `viewer_identifiers` (
  `identifier_id` int(11) NOT NULL,
  `viewer_id` int(20) NOT NULL DEFAULT '0',
  `type` enum('ip','cookie') NOT NULL DEFAULT 'ip',
  `identifier` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_mine` (`is_mine`),
  ADD KEY `vote_identifier` (`vote_identifier`),
  ADD KEY `option_index` (`option_index`),
  ADD KEY `primary_blockchain_id` (`primary_blockchain_id`);

--
-- Indexes for table `address_keys`
--
ALTER TABLE `address_keys`
  ADD PRIMARY KEY (`address_key_id`),
  ADD UNIQUE KEY `address_set_id` (`address_set_id`,`option_index`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `option_index` (`option_index`,`primary_blockchain_id`,`account_id`,`address_set_id`);

--
-- Indexes for table `address_sets`
--
ALTER TABLE `address_sets`
  ADD PRIMARY KEY (`address_set_id`),
  ADD KEY `game_id` (`game_id`,`applied`);

--
-- Indexes for table `async_email_deliveries`
--
ALTER TABLE `async_email_deliveries`
  ADD PRIMARY KEY (`delivery_id`);

--
-- Indexes for table `blockchains`
--
ALTER TABLE `blockchains`
  ADD PRIMARY KEY (`blockchain_id`),
  ADD KEY `creator_id` (`creator_id`);

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`internal_block_id`),
  ADD UNIQUE KEY `blockchain_id` (`blockchain_id`,`block_id`),
  ADD UNIQUE KEY `blockchain_id_2` (`blockchain_id`,`block_hash`),
  ADD KEY `blockchain_id_3` (`blockchain_id`,`locally_saved`),
  ADD KEY `blockchain_id_4` (`blockchain_id`,`time_loaded`),
  ADD KEY `blockchain_id_5` (`blockchain_id`,`time_mined`);

--
-- Indexes for table `cached_urls`
--
ALTER TABLE `cached_urls`
  ADD PRIMARY KEY (`cached_url_id`),
  ADD UNIQUE KEY `url` (`url`),
  ADD KEY `time_fetched` (`time_fetched`);

--
-- Indexes for table `cards`
--
ALTER TABLE `cards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `issuer_card_id` (`peer_card_id`),
  ADD KEY `design_id` (`design_id`);

--
-- Indexes for table `card_conversions`
--
ALTER TABLE `card_conversions`
  ADD PRIMARY KEY (`conversion_id`),
  ADD KEY `card_id` (`card_id`);

--
-- Indexes for table `card_currency_balances`
--
ALTER TABLE `card_currency_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD KEY `card_id` (`card_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `card_currency_denominations`
--
ALTER TABLE `card_currency_denominations`
  ADD PRIMARY KEY (`denomination_id`);

--
-- Indexes for table `card_designs`
--
ALTER TABLE `card_designs`
  ADD PRIMARY KEY (`design_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `image_id` (`image_id`);

--
-- Indexes for table `card_failedchecks`
--
ALTER TABLE `card_failedchecks`
  ADD PRIMARY KEY (`check_id`);

--
-- Indexes for table `card_printrequests`
--
ALTER TABLE `card_printrequests`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `card_sessions`
--
ALTER TABLE `card_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `card_user_id` (`card_user_id`);

--
-- Indexes for table `card_status_changes`
--
ALTER TABLE `card_status_changes`
  ADD PRIMARY KEY (`change_id`);

--
-- Indexes for table `card_users`
--
ALTER TABLE `card_users`
  ADD PRIMARY KEY (`card_user_id`);

--
-- Indexes for table `card_withdrawals`
--
ALTER TABLE `card_withdrawals`
  ADD PRIMARY KEY (`withdrawal_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `url_identifier` (`url_identifier`),
  ADD KEY `parent_category_id` (`parent_category_id`),
  ADD KEY `display_rank` (`display_rank`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`currency_id`),
  ADD KEY `oracle_url_id` (`oracle_url_id`),
  ADD KEY `abbreviation` (`abbreviation`),
  ADD KEY `blockchain_id` (`blockchain_id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `currency_accounts`
--
ALTER TABLE `currency_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `current_address_id` (`current_address_id`),
  ADD KEY `currency_id_2` (`currency_id`),
  ADD KEY `user_id_2` (`user_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `current_address_id_2` (`current_address_id`);

--
-- Indexes for table `currency_invoices`
--
ALTER TABLE `currency_invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `user_game_id` (`user_game_id`),
  ADD KEY `pay_currency_id` (`pay_currency_id`),
  ADD KEY `receive_address_id` (`receive_address_id`);

--
-- Indexes for table `currency_invoice_ios`
--
ALTER TABLE `currency_invoice_ios`
  ADD PRIMARY KEY (`invoice_io_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `currency_prices`
--
ALTER TABLE `currency_prices`
  ADD PRIMARY KEY (`price_id`),
  ADD KEY `currency_id_2` (`currency_id`,`reference_currency_id`,`time_added`),
  ADD KEY `cached_url_id` (`cached_url_id`);

--
-- Indexes for table `entities`
--
ALTER TABLE `entities`
  ADD PRIMARY KEY (`entity_id`),
  ADD UNIQUE KEY `entity_type_id_2` (`entity_type_id`,`entity_name`),
  ADD KEY `entity_type_id` (`entity_type_id`);

--
-- Indexes for table `entity_types`
--
ALTER TABLE `entity_types`
  ADD PRIMARY KEY (`entity_type_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD UNIQUE KEY `game_id_2` (`game_id`,`event_index`),
  ADD UNIQUE KEY `payout_transaction_id` (`payout_transaction_id`),
  ADD UNIQUE KEY `winning_option_id` (`winning_option_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `event_type_id` (`event_type_id`),
  ADD KEY `game_id_3` (`game_id`,`next_event_index`),
  ADD KEY `game_id_4` (`game_id`,`event_starting_block`),
  ADD KEY `game_id_5` (`game_id`,`event_final_block`),
  ADD KEY `outcome_index` (`outcome_index`),
  ADD KEY `sport_entity_id` (`sport_entity_id`),
  ADD KEY `league_entity_id` (`league_entity_id`),
  ADD KEY `game_id_6` (`game_id`,`event_payout_block`),
  ADD KEY `game_id_7` (`game_id`,`event_outcome_block`);

--
-- Indexes for table `event_types`
--
ALTER TABLE `event_types`
  ADD PRIMARY KEY (`event_type_id`),
  ADD KEY `option_group_id` (`option_group_id`),
  ADD KEY `url_identifier` (`url_identifier`);

--
-- Indexes for table `external_addresses`
--
ALTER TABLE `external_addresses`
  ADD PRIMARY KEY (`address_id`);

--
-- Indexes for table `featured_strategies`
--
ALTER TABLE `featured_strategies`
  ADD PRIMARY KEY (`featured_strategy_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `reference_account_id` (`reference_account_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `game_status` (`game_status`),
  ADD KEY `payout_weight` (`payout_weight`),
  ADD KEY `game_type_id` (`game_type_id`),
  ADD KEY `blockchain_id` (`blockchain_id`);

--
-- Indexes for table `game_blocks`
--
ALTER TABLE `game_blocks`
  ADD PRIMARY KEY (`game_block_id`),
  ADD UNIQUE KEY `game_id` (`game_id`,`block_id`),
  ADD KEY `block_id` (`block_id`),
  ADD KEY `locally_saved` (`locally_saved`);

--
-- Indexes for table `game_defined_escrow_amounts`
--
ALTER TABLE `game_defined_escrow_amounts`
  ADD PRIMARY KEY (`escrow_amount_id`),
  ADD UNIQUE KEY `game_id_2` (`game_id`,`currency_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `game_defined_events`
--
ALTER TABLE `game_defined_events`
  ADD PRIMARY KEY (`game_defined_event_id`),
  ADD UNIQUE KEY `game_id_2` (`game_id`,`event_index`),
  ADD KEY `game_id` (`game_id`);

--
-- Indexes for table `game_defined_options`
--
ALTER TABLE `game_defined_options`
  ADD PRIMARY KEY (`game_defined_option_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `event_index` (`event_index`),
  ADD KEY `option_index` (`option_index`);

--
-- Indexes for table `game_definitions`
--
ALTER TABLE `game_definitions`
  ADD PRIMARY KEY (`game_definition_id`),
  ADD KEY `definition_hash` (`definition_hash`);

--
-- Indexes for table `game_escrow_amounts`
--
ALTER TABLE `game_escrow_amounts`
  ADD PRIMARY KEY (`escrow_account_id`),
  ADD UNIQUE KEY `game_id_2` (`game_id`,`currency_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `game_invitations`
--
ALTER TABLE `game_invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD UNIQUE KEY `invitation_key` (`invitation_key`),
  ADD KEY `inviter_id` (`inviter_id`),
  ADD KEY `used` (`used`),
  ADD KEY `game_id` (`game_id`);

--
-- Indexes for table `game_peers`
--
ALTER TABLE `game_peers`
  ADD PRIMARY KEY (`game_peer_id`),
  ADD UNIQUE KEY `game_id` (`game_id`,`peer_id`);

--
-- Indexes for table `game_sellouts`
--
ALTER TABLE `game_sellouts`
  ADD PRIMARY KEY (`sellout_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `in_block_id` (`in_block_id`),
  ADD KEY `out_block_id` (`out_block_id`),
  ADD KEY `in_tx_hash` (`in_tx_hash`),
  ADD KEY `out_tx_hash` (`out_tx_hash`);

--
-- Indexes for table `game_types`
--
ALTER TABLE `game_types`
  ADD PRIMARY KEY (`game_type_id`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`image_id`),
  ADD UNIQUE KEY `image_identifier` (`image_identifier`),
  ADD KEY `access_key` (`access_key`);

--
-- Indexes for table `log_messages`
--
ALTER TABLE `log_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `mobile_payments`
--
ALTER TABLE `mobile_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `payment_type` (`payment_type`),
  ADD KEY `payment_status` (`payment_status`),
  ADD KEY `beyonic_request_id` (`beyonic_request_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`module_id`),
  ADD UNIQUE KEY `module_name` (`module_name`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`subscriber_id`),
  ADD UNIQUE KEY `email_address` (`email_address`);

--
-- Indexes for table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`option_id`),
  ADD UNIQUE KEY `event_id_2` (`event_id`,`event_option_index`),
  ADD KEY `voting_option_id` (`entity_id`),
  ADD KEY `image_id` (`image_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `option_index` (`option_index`);

--
-- Indexes for table `option_blocks`
--
ALTER TABLE `option_blocks`
  ADD PRIMARY KEY (`option_block_id`),
  ADD UNIQUE KEY `option_id_2` (`option_id`,`block_height`),
  ADD KEY `option_id` (`option_id`),
  ADD KEY `block_height` (`block_height`);

--
-- Indexes for table `option_groups`
--
ALTER TABLE `option_groups`
  ADD PRIMARY KEY (`group_id`);

--
-- Indexes for table `option_group_memberships`
--
ALTER TABLE `option_group_memberships`
  ADD PRIMARY KEY (`membership_id`),
  ADD KEY `option_group_id` (`option_group_id`),
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `oracle_urls`
--
ALTER TABLE `oracle_urls`
  ADD PRIMARY KEY (`oracle_url_id`);

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
-- Indexes for table `peers`
--
ALTER TABLE `peers`
  ADD PRIMARY KEY (`peer_id`);

--
-- Indexes for table `redirect_urls`
--
ALTER TABLE `redirect_urls`
  ADD PRIMARY KEY (`redirect_url_id`),
  ADD UNIQUE KEY `url` (`url`),
  ADD UNIQUE KEY `redirect_key` (`redirect_key`);

--
-- Indexes for table `site_constants`
--
ALTER TABLE `site_constants`
  ADD PRIMARY KEY (`constant_id`),
  ADD UNIQUE KEY `constant_name` (`constant_name`);

--
-- Indexes for table `strategy_round_allocations`
--
ALTER TABLE `strategy_round_allocations`
  ADD PRIMARY KEY (`allocation_id`),
  ADD UNIQUE KEY `strategy_id_2` (`strategy_id`,`round_id`,`option_id`),
  ADD KEY `strategy_id` (`strategy_id`),
  ADD KEY `round_id` (`round_id`),
  ADD KEY `option_id` (`option_id`),
  ADD KEY `strategy_id_3` (`strategy_id`,`round_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD UNIQUE KEY `game_id` (`blockchain_id`,`tx_hash`),
  ADD UNIQUE KEY `blockchain_id` (`blockchain_id`,`block_id`,`position_in_block`),
  ADD KEY `block_id` (`block_id`),
  ADD KEY `event_id` (`blockchain_id`),
  ADD KEY `transaction_desc` (`transaction_desc`,`blockchain_id`);

--
-- Indexes for table `transaction_game_ios`
--
ALTER TABLE `transaction_game_ios`
  ADD PRIMARY KEY (`game_io_id`),
  ADD UNIQUE KEY `game_id_2` (`game_id`,`game_io_index`),
  ADD KEY `io_id` (`io_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `option_id` (`option_id`),
  ADD KEY `parent_io_id` (`parent_io_id`),
  ADD KEY `payout_io_id` (`payout_io_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `resolved_before_spent` (`resolved_before_spent`);

--
-- Indexes for table `transaction_ios`
--
ALTER TABLE `transaction_ios`
  ADD PRIMARY KEY (`io_id`),
  ADD UNIQUE KEY `create_transaction_id_2` (`create_transaction_id`,`out_index`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `spend_status` (`spend_status`),
  ADD KEY `create_transaction_id` (`create_transaction_id`),
  ADD KEY `spend_transaction_id` (`spend_transaction_id`),
  ADD KEY `create_block_id` (`create_block_id`),
  ADD KEY `spend_block_id` (`spend_block_id`),
  ADD KEY `option_index` (`option_index`),
  ADD KEY `blockchain_id` (`blockchain_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `password` (`password`);

--
-- Indexes for table `user_games`
--
ALTER TABLE `user_games`
  ADD PRIMARY KEY (`user_game_id`),
  ADD UNIQUE KEY `account_id` (`account_id`),
  ADD KEY `strategy_id` (`strategy_id`),
  ADD KEY `user_id` (`user_id`,`game_id`);

--
-- Indexes for table `user_login_links`
--
ALTER TABLE `user_login_links`
  ADD PRIMARY KEY (`login_link_id`),
  ADD UNIQUE KEY `access_key` (`access_key`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `user_strategy_blocks`
--
ALTER TABLE `user_strategy_blocks`
  ADD PRIMARY KEY (`strategy_block_id`),
  ADD UNIQUE KEY `strategy_id` (`strategy_id`,`block_within_round`),
  ADD KEY `strategy_id_2` (`strategy_id`),
  ADD KEY `block_within_round` (`block_within_round`);

--
-- Indexes for table `user_strategy_entities`
--
ALTER TABLE `user_strategy_entities`
  ADD PRIMARY KEY (`strategy_option_id`),
  ADD UNIQUE KEY `strategy_id_2` (`strategy_id`,`entity_id`),
  ADD KEY `strategy_id` (`strategy_id`);

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
  ADD PRIMARY KEY (`identifier_id`),
  ADD UNIQUE KEY `type` (`type`,`identifier`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `address_keys`
--
ALTER TABLE `address_keys`
  MODIFY `address_key_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `address_sets`
--
ALTER TABLE `address_sets`
  MODIFY `address_set_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `async_email_deliveries`
--
ALTER TABLE `async_email_deliveries`
  MODIFY `delivery_id` int(12) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockchains`
--
ALTER TABLE `blockchains`
  MODIFY `blockchain_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `internal_block_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cached_urls`
--
ALTER TABLE `cached_urls`
  MODIFY `cached_url_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cards`
--
ALTER TABLE `cards`
  MODIFY `card_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_conversions`
--
ALTER TABLE `card_conversions`
  MODIFY `conversion_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_currency_balances`
--
ALTER TABLE `card_currency_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_currency_denominations`
--
ALTER TABLE `card_currency_denominations`
  MODIFY `denomination_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `card_designs`
--
ALTER TABLE `card_designs`
  MODIFY `design_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_failedchecks`
--
ALTER TABLE `card_failedchecks`
  MODIFY `check_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_printrequests`
--
ALTER TABLE `card_printrequests`
  MODIFY `request_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_sessions`
--
ALTER TABLE `card_sessions`
  MODIFY `session_id` int(22) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_status_changes`
--
ALTER TABLE `card_status_changes`
  MODIFY `change_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_users`
--
ALTER TABLE `card_users`
  MODIFY `card_user_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `card_withdrawals`
--
ALTER TABLE `card_withdrawals`
  MODIFY `withdrawal_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `currency_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `currency_accounts`
--
ALTER TABLE `currency_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_invoices`
--
ALTER TABLE `currency_invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_invoice_ios`
--
ALTER TABLE `currency_invoice_ios`
  MODIFY `invoice_io_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_prices`
--
ALTER TABLE `currency_prices`
  MODIFY `price_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `entities`
--
ALTER TABLE `entities`
  MODIFY `entity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `entity_types`
--
ALTER TABLE `entity_types`
  MODIFY `entity_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_types`
--
ALTER TABLE `event_types`
  MODIFY `event_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_addresses`
--
ALTER TABLE `external_addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `featured_strategies`
--
ALTER TABLE `featured_strategies`
  MODIFY `featured_strategy_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_blocks`
--
ALTER TABLE `game_blocks`
  MODIFY `game_block_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_defined_escrow_amounts`
--
ALTER TABLE `game_defined_escrow_amounts`
  MODIFY `escrow_amount_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_defined_events`
--
ALTER TABLE `game_defined_events`
  MODIFY `game_defined_event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_defined_options`
--
ALTER TABLE `game_defined_options`
  MODIFY `game_defined_option_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_definitions`
--
ALTER TABLE `game_definitions`
  MODIFY `game_definition_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_escrow_amounts`
--
ALTER TABLE `game_escrow_amounts`
  MODIFY `escrow_account_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_invitations`
--
ALTER TABLE `game_invitations`
  MODIFY `invitation_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_peers`
--
ALTER TABLE `game_peers`
  MODIFY `game_peer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_sellouts`
--
ALTER TABLE `game_sellouts`
  MODIFY `sellout_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_types`
--
ALTER TABLE `game_types`
  MODIFY `game_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `log_messages`
--
ALTER TABLE `log_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mobile_payments`
--
ALTER TABLE `mobile_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `module_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `options`
--
ALTER TABLE `options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `option_blocks`
--
ALTER TABLE `option_blocks`
  MODIFY `option_block_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `option_groups`
--
ALTER TABLE `option_groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `option_group_memberships`
--
ALTER TABLE `option_group_memberships`
  MODIFY `membership_id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `oracle_urls`
--
ALTER TABLE `oracle_urls`
  MODIFY `oracle_url_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `peers`
--
ALTER TABLE `peers`
  MODIFY `peer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `redirect_urls`
--
ALTER TABLE `redirect_urls`
  MODIFY `redirect_url_id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_constants`
--
ALTER TABLE `site_constants`
  MODIFY `constant_id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `strategy_round_allocations`
--
ALTER TABLE `strategy_round_allocations`
  MODIFY `allocation_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_game_ios`
--
ALTER TABLE `transaction_game_ios`
  MODIFY `game_io_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_ios`
--
ALTER TABLE `transaction_ios`
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
-- AUTO_INCREMENT for table `user_login_links`
--
ALTER TABLE `user_login_links`
  MODIFY `login_link_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `user_strategy_blocks`
--
ALTER TABLE `user_strategy_blocks`
  MODIFY `strategy_block_id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_strategy_entities`
--
ALTER TABLE `user_strategy_entities`
  MODIFY `strategy_option_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `viewers`
--
ALTER TABLE `viewers`
  MODIFY `viewer_id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
COMMIT;
