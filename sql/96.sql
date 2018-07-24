ALTER TABLE `currency_accounts` ADD `has_option_indices_until` INT NULL DEFAULT -1 AFTER `time_created`;
ALTER TABLE `user_games` ADD UNIQUE (`account_id`);