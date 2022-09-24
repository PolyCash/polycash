ALTER TABLE `game_peers` ADD COLUMN `last_sync_check_at` INT NULL DEFAULT NULL AFTER `peer_id`;
ALTER TABLE `transaction_game_ios` ADD COLUMN `partition_checksum` VARCHAR(64) NULL DEFAULT NULL AFTER `spend_round_id`;
