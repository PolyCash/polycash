ALTER TABLE `transaction_ios` DROP ` memo `;
ALTER TABLE `transaction_ios` ADD INDEX (`create_round_id`);
ALTER TABLE `transaction_ios` ADD INDEX (`spend_round_id`);
ALTER TABLE `games` ADD `game_starting_block` INT NOT NULL DEFAULT '0' AFTER `rpc_password`;
ALTER TABLE `cached_rounds` ADD `derived_winning_option_id` INT NULL DEFAULT NULL AFTER `winning_score`;
ALTER TABLE `cached_rounds` ADD `derived_winning_score` BIGINT(20) NOT NULL DEFAULT '0' AFTER `derived_winning_option_id`;
ALTER TABLE `cached_rounds` ADD INDEX (`derived_winning_option_id`);
