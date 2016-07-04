ALTER TABLE transactions DROP INDEX transaction_desc;
ALTER TABLE `transactions` ADD INDEX (`transaction_desc`, `game_id`);
ALTER TABLE `transactions` ADD INDEX (`round_id`);
ALTER TABLE `games` ADD `coins_in_existence` BIGINT(20) NOT NULL DEFAULT '0' AFTER `sync_coind_by_cron`, ADD `coins_in_existence_block` INT NULL DEFAULT NULL AFTER `coins_in_existence`;
ALTER TABLE `games` ADD `currency_id` INT NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `games` ADD INDEX (`currency_id`);
ALTER TABLE `transactions` DROP `vote_transaction_id`;
ALTER TABLE `transactions` DROP `address_id`;

