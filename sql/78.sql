ALTER TABLE `card_printrequests` ADD `secrets_present` TINYINT(1) NOT NULL DEFAULT '0' AFTER `pay_status`;
ALTER TABLE `cards` ADD `io_tx_hash` VARCHAR(64) NULL DEFAULT NULL AFTER `reseller_sale_id`, ADD `io_out_index` INT NULL DEFAULT NULL AFTER `io_tx_hash`, ADD `io_id` INT NULL DEFAULT NULL AFTER `io_out_index`;
ALTER TABLE `card_printrequests` DROP `lockedin_price`;
ALTER TABLE `currencies` ADD `default_design_text_color` VARCHAR(100) NULL DEFAULT NULL AFTER `default_design_image_id`;
UPDATE currencies SET default_design_text_color='dark' WHERE name='StakeChain';
ALTER TABLE `cards` CHANGE `redeemer_id` `user_id` INT(20) NULL DEFAULT NULL;
ALTER TABLE `cards` CHANGE `status` `status` ENUM('issued','printed','assigned','sold','redeemed','canceled','claimed') CHARACTER SET latin1 COLLATE latin1_german2_ci NOT NULL;
ALTER TABLE `cards` ADD `claim_time` INT NULL DEFAULT NULL AFTER `redeem_time`;

CREATE TABLE `card_issuers` (
  `issuer_id` int(11) NOT NULL,
  `issuer_identifier` varchar(100) DEFAULT NULL,
  `issuer_name` varchar(100) DEFAULT NULL,
  `base_url` VARCHAR(255) NULL DEFAULT NULL,
  `time_created` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `card_issuers`
  ADD PRIMARY KEY (`issuer_id`);

ALTER TABLE `card_issuers`
  MODIFY `issuer_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

ALTER TABLE `cards` ADD `issuer_id` INT NULL DEFAULT NULL AFTER `design_id`;
UPDATE cards c JOIN card_designs d ON c.design_id=d.design_id SET c.issuer_id=d.issuer_id;
ALTER TABLE `card_designs` DROP `issuer_id`;