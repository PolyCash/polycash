CREATE TABLE `currency_invoice_ios` (
  `invoice_io_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `tx_hash` varchar(64) DEFAULT NULL,
  `out_index` int(11) DEFAULT NULL,
  `game_out_index` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `currency_invoice_ios`
  ADD PRIMARY KEY (`invoice_io_id`);

ALTER TABLE `currency_invoice_ios`
  MODIFY `invoice_io_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

ALTER TABLE `currency_invoice_ios` ADD INDEX (`invoice_id`);
