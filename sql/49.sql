ALTER TABLE `games` DROP `seconds_per_block`;
ALTER TABLE `blockchains` ADD `last_hash_time` INT NULL DEFAULT NULL AFTER `supports_getblockheader`;