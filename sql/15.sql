ALTER TABLE `user_games` CHANGE `account_value` `account_value` DECIMAL(16,8) NOT NULL DEFAULT '0.00000000';
ALTER TABLE `transaction_ios` ADD `spend_transaction_ids` VARCHAR(100) NOT NULL DEFAULT '' AFTER `spend_count`;
UPDATE transaction_ios SET spend_transaction_ids=CONCAT(spend_transaction_id, ",") WHERE spend_transaction_id IS NOT NULL;
