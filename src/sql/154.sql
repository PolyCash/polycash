ALTER TABLE `user_sessions` ADD `synchronizer_token` VARCHAR(32) NULL DEFAULT NULL AFTER `ip_address`;
ALTER TABLE `card_sessions` ADD `synchronizer_token` VARCHAR(32) NULL DEFAULT NULL AFTER `ip_address`;
