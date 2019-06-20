ALTER TABLE `currency_prices` ADD `cached_url_id` INT NULL DEFAULT NULL AFTER `reference_currency_id`;
ALTER TABLE `currency_prices` ADD INDEX (`cached_url_id`);