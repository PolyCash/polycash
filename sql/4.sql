ALTER TABLE `users` ADD `salt` VARCHAR(16) NOT NULL DEFAULT '' AFTER `api_access_code`;
