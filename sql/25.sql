CREATE TABLE `game_sellouts` (
  `sellout_id` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `block_id` int(11) DEFAULT NULL,
  `in_tx_hash` varchar(64) DEFAULT NULL,
  `out_tx_hash` varchar(64) DEFAULT NULL,
  `color_amount_in` bigint(20) NOT NULL DEFAULT '0',
  `exchange_rate` decimal(16,8) NOT NULL DEFAULT '0.00000000',
  `amount_in` bigint(20) NOT NULL DEFAULT '0',
  `amount_out` bigint(20) NOT NULL DEFAULT '0',
  `out_amounts` varchar(255) NOT NULL DEFAULT '',
  `fee_amount` bigint(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `game_sellouts`
  ADD PRIMARY KEY (`sellout_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `block_id` (`block_id`),
  ADD KEY `in_tx_hash` (`in_tx_hash`),
  ADD KEY `out_tx_hash` (`out_tx_hash`);

ALTER TABLE `game_sellouts`
  MODIFY `sellout_id` int(11) NOT NULL AUTO_INCREMENT;