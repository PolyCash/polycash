ALTER TABLE `viewers` ADD `left_menu_open` TINYINT(1) NOT NULL DEFAULT '1' AFTER `account_id`;
ALTER TABLE `users` ADD `left_menu_open` TINYINT(1) NOT NULL DEFAULT '1' AFTER `authorized_games`;