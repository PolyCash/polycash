ALTER TABLE `entities` 
ADD COLUMN `hp` INT NULL DEFAULT NULL AFTER `forex_pair_shows_nonstandard`,
ADD COLUMN `best_attack_name` VARCHAR(45) NULL DEFAULT NULL AFTER `hp`,
ADD COLUMN `level` INT(11) NULL DEFAULT NULL AFTER `best_attack_name`,
ADD COLUMN `color` VARCHAR(45) NULL DEFAULT NULL AFTER `level`,
ADD COLUMN `body_shape` VARCHAR(45) NULL DEFAULT NULL AFTER `color`;
