ALTER TABLE `game_escrow_amounts` DROP INDEX `game_id`;
ALTER TABLE `game_escrow_amounts` DROP INDEX `currency_id`;
ALTER TABLE `game_escrow_amounts` DROP INDEX `game_id_2`;
ALTER TABLE `game_escrow_amounts` CHANGE `escrow_account_id` `escrow_amount_id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `game_defined_escrow_amounts` DROP INDEX `currency_id`;
ALTER TABLE `game_defined_escrow_amounts` DROP INDEX `game_id`;
ALTER TABLE `game_defined_escrow_amounts` DROP INDEX `game_id_2`;

ALTER TABLE `game_defined_escrow_amounts` ADD `escrow_type` VARCHAR(20) NOT NULL DEFAULT 'fixed' AFTER `amount`, ADD `relative_amount` DECIMAL(20,10) NULL DEFAULT NULL AFTER `escrow_type`, ADD `escrow_position` INT NULL DEFAULT NULL AFTER `relative_amount`;

ALTER TABLE `game_escrow_amounts` ADD `escrow_type` VARCHAR(20) NOT NULL DEFAULT 'fixed' AFTER `amount`, ADD `relative_amount` DECIMAL(20,10) NULL DEFAULT NULL AFTER `escrow_type`, ADD `escrow_position` INT NULL DEFAULT NULL AFTER `relative_amount`;

ALTER TABLE `game_escrow_amounts` ADD INDEX (`game_id`, `escrow_position`, `currency_id`);
ALTER TABLE `game_defined_escrow_amounts` ADD INDEX (`game_id`, `escrow_position`, `currency_id`);
