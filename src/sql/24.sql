ALTER TABLE `games` ADD `sellout_policy` ENUM('on','off') NOT NULL DEFAULT 'on' AFTER `game_buyin_cap`;
ALTER TABLE `games` ADD `sellout_confirmations` INT NULL DEFAULT NULL AFTER `sellout_policy`;