ALTER TABLE blocks DROP INDEX block_hash;
ALTER TABLE `blocks` ADD UNIQUE (`game_id`, `block_hash`);
UPDATE `game_types` SET `sync_coind_by_cron` = '1' WHERE `game_type_id` = 2;
UPDATE games g JOIN game_types gt ON g.game_type_id=gt.game_type_id SET g.sync_coind_by_cron=gt.sync_coind_by_cron;