ALTER TABLE `address_keys` ADD `used_in_my_tx` TINYINT(1) NOT NULL DEFAULT '0' AFTER `address_set_id`;
ALTER TABLE `address_keys` ADD INDEX (`used_in_my_tx`);
