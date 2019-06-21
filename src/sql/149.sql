ALTER TABLE `blocks` ADD `transactions_html` MEDIUMBLOB NULL DEFAULT NULL AFTER `sec_since_prev_block`;
ALTER TABLE `blocks` DROP INDEX `miner_user_id`;