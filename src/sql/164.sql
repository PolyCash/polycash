ALTER TABLE `transaction_game_ios` ADD `address_id` INT NULL DEFAULT NULL AFTER `io_id`;
UPDATE `transaction_game_ios` gio JOIN `transaction_ios` io ON gio.io_id=gio.io_id SET gio.address_id=io.address_id;
ALTER TABLE `transaction_game_ios` ADD INDEX (`address_id`);
ALTER TABLE `transaction_game_ios` ADD INDEX (`resolved_before_spent`);
