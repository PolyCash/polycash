ALTER TABLE `address_keys` ADD `priv_key` VARCHAR(255) NULL DEFAULT NULL AFTER `pub_key`;
ALTER TABLE `address_keys` ADD `associated_email_address` VARCHAR(255) NULL DEFAULT NULL AFTER `priv_enc`;
ALTER TABLE `address_keys` ADD `access_key` VARCHAR(32) NULL DEFAULT NULL AFTER `account_id`;