ALTER TABLE `user_games` ADD `account_id` INT NULL DEFAULT NULL AFTER `strategy_id`;
ALTER TABLE `transactions` DROP `from_user_id`, DROP `to_user_id`;
ALTER TABLE `users` DROP `bitcoin_address_id`;