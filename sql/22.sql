ALTER TABLE `blockchains` ADD `seconds_per_block` INT NULL DEFAULT NULL AFTER `coin_name_plural`;
UPDATE blockchains SET seconds_per_block=150 WHERE url_identifier='litecoin';
UPDATE blockchains SET seconds_per_block=600 WHERE url_identifier='bitcoin';