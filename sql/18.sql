CREATE TABLE `currency_accounts` (
  `account_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `current_address_id` int(11) DEFAULT NULL,
  `account_name` varchar(100) NOT NULL,
  `time_created` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `currency_addresses` (
  `currency_address_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `pub_key` varchar(40) NOT NULL DEFAULT '',
  `priv_enc` varchar(300) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `currency_ios` (
  `io_id` int(11) NOT NULL,
  `currency_address_id` int(11) DEFAULT NULL,
  `amount` bigint(20) NOT NULL DEFAULT '0',
  `out_index` int(11) NOT NULL DEFAULT '0',
  `spend_status` enum('unspent','spent','unconfirmed') NOT NULL DEFAULT 'unconfirmed',
  `create_transaction_id` int(11) DEFAULT NULL,
  `spend_transaction_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `currency_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `current_address_id` (`current_address_id`);

ALTER TABLE `currency_addresses`
  ADD PRIMARY KEY (`currency_address_id`),
  ADD KEY `currency_id` (`currency_id`);

ALTER TABLE `currency_ios`
  ADD PRIMARY KEY (`io_id`),
  ADD KEY `currency_address_id` (`currency_address_id`),
  ADD KEY `out_index` (`out_index`),
  ADD KEY `create_transaction_id` (`create_transaction_id`),
  ADD KEY `spend_transaction_id` (`spend_transaction_id`);