ALTER TABLE `games` ADD `p2p_mode` ENUM('rpc','none') NOT NULL DEFAULT 'none' AFTER `game_type`;
UPDATE games SET p2p_mode='none';
UPDATE games SET p2p_mode='rpc' WHERE game_type='real';
ALTER TABLE `games` DROP `game_type`;
ALTER TABLE `game_types` ADD `p2p_mode` ENUM('rpc','none') NOT NULL DEFAULT 'none' AFTER `game_type`;
UPDATE game_types SET p2p_mode='none';
UPDATE game_types SET p2p_mode='rpc' WHERE game_type='real';
ALTER TABLE `game_types` DROP `game_type`;