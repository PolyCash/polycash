ALTER TABLE `user_games` CHANGE `account_value` `account_value` DECIMAL(20,8) NOT NULL DEFAULT '0.00000000';
ALTER TABLE `user_games` ADD `faucet_claims` INT NULL DEFAULT '0' AFTER `api_access_code`;