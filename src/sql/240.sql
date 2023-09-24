ALTER TABLE `users` CHANGE `first_name` `first_name` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_german2_ci NULL DEFAULT NULL, CHANGE `last_name` `last_name` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_german2_ci NULL DEFAULT NULL;
UPDATE users SET first_name=NULL WHERE first_name='';
UPDATE users SET last_name=NULL WHERE last_name='';
