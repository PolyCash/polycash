ALTER TABLE `blockchains` ADD `default_image_id` INT NULL DEFAULT NULL AFTER `only_game_id`;
UPDATE blockchains SET default_image_id=73 WHERE blockchain_name='Litecoin';
UPDATE blockchains SET default_image_id=35 WHERE blockchain_name='Bitcoin';
ALTER TABLE `blockchains` ADD `rpc_last_time_connected` INT NULL DEFAULT NULL AFTER `last_hash_time`, ADD `block_height` INT NULL DEFAULT NULL AFTER `rpc_last_time_connected`;