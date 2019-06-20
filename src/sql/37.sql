ALTER TABLE `transaction_ios` ADD INDEX (`blockchain_id`);
ALTER TABLE `game_blocks` ADD INDEX (`block_id`);
ALTER TABLE `game_blocks` ADD INDEX (`locally_saved`);