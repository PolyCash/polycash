CREATE TABLE `cards` (
  `card_id` int(20) NOT NULL,
  `issuer_card_id` int(20) DEFAULT NULL,
  `group_id` int(20) NOT NULL,
  `design_id` int(20) DEFAULT NULL,
  `unlock_time` int(20) DEFAULT NULL,
  `mint_time` int(20) NOT NULL,
  `currency_id` int(20) NOT NULL DEFAULT '1',
  `fv_currency_id` int(11) DEFAULT NULL,
  `amount` float NOT NULL,
  `purity` int(8) NOT NULL DEFAULT '100',
  `secret` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `status` enum('issued','printed','assigned','sold','redeemed','canceled') COLLATE latin1_german2_ci NOT NULL,
  `redeem_time` int(20) NOT NULL DEFAULT '0',
  `redeemer_id` int(20) NOT NULL DEFAULT '0',
  `card_user_id` int(20) DEFAULT NULL,
  `card_group_id` int(12) DEFAULT NULL,
  `reseller_sale_id` int(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;
  
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
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

CREATE TABLE `card_currency_balances` (
  `balance_id` int(11) NOT NULL,
  `card_id` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `balance` double NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `card_currency_denominations` (
  `denomination_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `fv_currency_id` int(11) DEFAULT NULL,
  `denomination` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  `purity` varchar(20) COLLATE latin1_german2_ci NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

CREATE TABLE `card_failedchecks` (
  `check_id` int(20) NOT NULL,
  `card_id` int(20) DEFAULT NULL,
  `ip_address` varchar(100) NOT NULL DEFAULT '',
  `check_time` int(20) NOT NULL DEFAULT '0',
  `attempted_code` varchar(100) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `card_printrequests` (
  `request_id` int(20) NOT NULL,
  `design_id` int(20) DEFAULT NULL,
  `user_id` int(20) DEFAULT NULL,
  `address_id` int(20) NOT NULL DEFAULT '0',
  `card_group_id` int(20) DEFAULT NULL,
  `how_many` int(20) NOT NULL DEFAULT '0',
  `lockedin_price` double NOT NULL DEFAULT '0',
  `print_status` enum('not-printed','printed','canceled') COLLATE latin1_german2_ci NOT NULL DEFAULT 'not-printed',
  `pay_status` enum('not-received','received') COLLATE latin1_german2_ci NOT NULL DEFAULT 'not-received',
  `time_created` int(20) NOT NULL DEFAULT '0',
  `time_payment_sent` int(20) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

CREATE TABLE `card_sessions` (
  `session_id` int(22) NOT NULL,
  `card_user_id` int(20) NOT NULL DEFAULT '0',
  `login_type` enum('default','superuser') COLLATE latin1_german2_ci NOT NULL DEFAULT 'default',
  `session_key` varchar(32) COLLATE latin1_german2_ci NOT NULL DEFAULT '',
  `login_time` int(12) NOT NULL DEFAULT '0',
  `logout_time` int(12) DEFAULT NULL,
  `expire_time` int(12) NOT NULL DEFAULT '0',
  `ip_address` varchar(30) COLLATE latin1_german2_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

CREATE TABLE `card_status_changes` (
  `change_id` int(11) NOT NULL,
  `card_id` int(20) NOT NULL DEFAULT '0',
  `from_status` varchar(20) COLLATE latin1_german2_ci NOT NULL,
  `to_status` varchar(20) COLLATE latin1_german2_ci NOT NULL,
  `change_time` varchar(20) COLLATE latin1_german2_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

CREATE TABLE `card_users` (
  `card_user_id` int(20) NOT NULL,
  `card_id` int(20) NOT NULL DEFAULT '0',
  `create_time` int(20) NOT NULL DEFAULT '0',
  `create_ip` varchar(100) COLLATE latin1_german2_ci NOT NULL,
  `password` varchar(100) COLLATE latin1_german2_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_german2_ci;

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

ALTER TABLE `currencies` ADD `default_design_image_id` INT NULL DEFAULT NULL AFTER `blockchain_id`;
UPDATE `currencies` SET `default_design_image_id` = '80' WHERE `currency_id` = 6;

ALTER TABLE `card_printrequests` ADD `issuer_id` INT NULL DEFAULT NULL AFTER `card_group_id`;
ALTER TABLE `card_designs` ADD `issuer_id` INT NULL DEFAULT NULL AFTER `image_id`;
ALTER TABLE `cards` ADD `secret_hash` VARCHAR(100) NULL DEFAULT NULL AFTER `secret`;
ALTER TABLE `card_designs` ADD `text_color` VARCHAR(100) NULL DEFAULT NULL AFTER `purity`;

ALTER TABLE `images` ADD `px_from_left` INT NULL DEFAULT NULL AFTER `extension`, ADD `px_from_top` INT NULL DEFAULT NULL AFTER `px_from_left`, ADD `width` INT NULL DEFAULT NULL AFTER `px_from_top`, ADD `height` INT NULL DEFAULT NULL AFTER `width`;
INSERT INTO `images` (`image_id`, `access_key`, `extension`, `px_from_left`, `px_from_top`, `width`, `height`) VALUES (80, '', 'png', -606, -360, 1410, 1410);

ALTER TABLE `cards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `issuer_card_id` (`issuer_card_id`),
  ADD KEY `design_id` (`design_id`);

ALTER TABLE `card_conversions`
  ADD PRIMARY KEY (`conversion_id`),
  ADD KEY `card_id` (`card_id`);

ALTER TABLE `card_currency_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD KEY `card_id` (`card_id`),
  ADD KEY `currency_id` (`currency_id`);

ALTER TABLE `card_currency_denominations`
  ADD PRIMARY KEY (`denomination_id`);

ALTER TABLE `card_designs`
  ADD PRIMARY KEY (`design_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `image_id` (`image_id`);

ALTER TABLE `card_failedchecks`
  ADD PRIMARY KEY (`check_id`);

ALTER TABLE `card_printrequests`
  ADD PRIMARY KEY (`request_id`);

ALTER TABLE `card_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `card_user_id` (`card_user_id`);

ALTER TABLE `card_status_changes`
  ADD PRIMARY KEY (`change_id`);

ALTER TABLE `card_users`
  ADD PRIMARY KEY (`card_user_id`);

ALTER TABLE `card_withdrawals`
  ADD PRIMARY KEY (`withdrawal_id`);

ALTER TABLE `mobile_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `payment_type` (`payment_type`),
  ADD KEY `payment_status` (`payment_status`),
  ADD KEY `beyonic_request_id` (`beyonic_request_id`);

ALTER TABLE `cards` MODIFY `card_id` int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_conversions` MODIFY `conversion_id` int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_currency_balances` MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_currency_denominations` MODIFY `denomination_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_designs` MODIFY `design_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_failedchecks` MODIFY `check_id` int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_printrequests` MODIFY `request_id` int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_sessions` MODIFY `session_id` int(22) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_status_changes` MODIFY `change_id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_users` MODIFY `card_user_id` int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `card_withdrawals` MODIFY `withdrawal_id` int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE `mobile_payments` MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

INSERT INTO `card_currency_denominations` (`currency_id`, `fv_currency_id`, `denomination`) VALUES
(6, 1, '1'),
(6, 1, '5'),
(6, 1, '20'),
(6, 1, '50'),
(6, 1, '100'),
(6, 1, '500'),
(6, 6, '0.01'),
(6, 6, '0.05'),
(6, 6, '0.1'),
(6, 6, '0.5'),
(6, 6, '1'),
(6, 6, '2'),
(6, 6, '5'),
(16, 16, '1'),
(16, 16, '5'),
(16, 16, '10'),
(16, 16, '50'),
(16, 16, '20');
