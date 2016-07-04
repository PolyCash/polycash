ALTER TABLE `games` ADD `rpc_port` INT(11) NULL DEFAULT NULL AFTER `game_status`, ADD `rpc_username` VARCHAR(255) NULL DEFAULT NULL AFTER `rpc_port`, ADD `rpc_password` VARCHAR(255) NULL DEFAULT NULL AFTER `rpc_username`;
ALTER TABLE `games` ADD `always_generate_coins` TINYINT(1) NOT NULL DEFAULT '0' AFTER `invitation_link`, ADD `restart_generation_seconds` INT NOT NULL DEFAULT '30' AFTER `always_generate_coins`;
ALTER TABLE `games` ADD `min_unallocated_addresses` INT NOT NULL DEFAULT '2' AFTER `restart_generation_seconds`;
ALTER TABLE `games` ADD `sync_coind_by_cron` TINYINT(1) NOT NULL DEFAULT '0' AFTER `min_unallocated_addresses`;

