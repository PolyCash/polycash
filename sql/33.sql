ALTER TABLE `blocks` ADD UNIQUE (`blockchain_id`, `block_hash`);
ALTER TABLE `address_keys` CHANGE `save_method` `save_method` ENUM('db','wallet.dat','fake') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'db';
ALTER TABLE `blockchains` ADD `creator_id` INT NULL DEFAULT NULL AFTER `blockchain_id`;
ALTER TABLE `blockchains` ADD INDEX (`creator_id`);