ALTER TABLE `games` ADD `url_identifier` VARCHAR(100) NULL DEFAULT NULL AFTER `creator_game_index`;
ALTER TABLE `cached_rounds` ADD `payout_transaction_id` INT(20) NULL DEFAULT NULL AFTER `payout_block_id`;
ALTER TABLE `empirecoin`.`cached_rounds` ADD UNIQUE (`payout_transaction_id`);
