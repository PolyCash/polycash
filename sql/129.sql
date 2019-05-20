ALTER TABLE `blockchains` ADD `rpc_host` VARCHAR(255) NULL DEFAULT NULL AFTER `decimal_places`;
UPDATE blockchains SET rpc_host='127.0.0.1' WHERE rpc_host IS NULL;
