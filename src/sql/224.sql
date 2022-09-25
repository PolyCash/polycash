ALTER TABLE `games` ADD COLUMN `last_reset_checksums_at` INT NULL DEFAULT NULL AFTER `extra_info`;
CREATE INDEX `idx_transaction_game_ios_game_id_partition_checksum` ON `transaction_game_ios` (game_id, partition_checksum);
ALTER TABLE `game_peers` 
ADD COLUMN `last_check_in_sync` TINYINT(1) NULL DEFAULT NULL AFTER `last_sync_check_at`,
ADD COLUMN `out_of_sync_since` INT NULL DEFAULT NULL AFTER `last_check_in_sync`,
ADD COLUMN `out_of_sync_block` INT NULL DEFAULT NULL AFTER `out_of_sync_since`;
