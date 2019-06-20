ALTER TABLE `transaction_ios` ADD `is_separator` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_destroy`;
UPDATE transaction_ios io JOIN addresses a ON io.address_id=a.address_id SET io.is_separator=a.is_separator_address;
