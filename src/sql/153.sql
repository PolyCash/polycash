ALTER TABLE `address_keys` ADD `option_index` INT NULL DEFAULT NULL AFTER `account_id`, ADD `primary_blockchain_id` INT NULL DEFAULT NULL AFTER `option_index`, ADD `address_set_id` INT NULL DEFAULT NULL AFTER `primary_blockchain_id`;

UPDATE address_keys k JOIN addresses a ON k.address_id=a.address_id SET k.option_index=a.option_index, k.primary_blockchain_id=a.primary_blockchain_id, k.address_set_id=a.address_set_id;

ALTER TABLE `address_keys` ADD INDEX (`option_index`, `primary_blockchain_id`, `account_id`, `address_set_id`);
ALTER TABLE `address_keys` ADD UNIQUE (`address_set_id`, `option_index`);

ALTER TABLE `address_keys` DROP `save_method`;

ALTER TABLE `addresses` DROP INDEX `address_set_id`;
ALTER TABLE `addresses` DROP INDEX `address_set_id_2`;
ALTER TABLE `addresses` DROP `address_set_id`;
