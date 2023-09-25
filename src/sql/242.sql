ALTER TABLE `users` ADD `has_legacy_password` TINYINT(1) NOT NULL DEFAULT '0' AFTER `backups_enabled`;
UPDATE users SET has_legacy_password=1 WHERE login_method='password' AND time_created < 1695603319;
