ALTER TABLE `peers` DROP COLUMN `visible`;
ALTER TABLE `game_peers` ADD COLUMN `disabled_at` INT NULL DEFAULT NULL AFTER `out_of_sync_block`;
