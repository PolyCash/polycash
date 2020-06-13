ALTER TABLE `games` ADD `order_options_by` ENUM('bets','option_index') NOT NULL DEFAULT 'bets' AFTER `bulk_add_blocks`;
