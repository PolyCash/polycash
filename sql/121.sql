ALTER TABLE `addresses` ADD `is_separator_address` TINYINT NOT NULL DEFAULT '0' AFTER `is_destroy_address`;
UPDATE addresses SET is_separator_address=1 WHERE option_index=1;