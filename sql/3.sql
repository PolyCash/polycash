ALTER TABLE `blocks` ADD `locally_saved` TINYINT(1) NOT NULL DEFAULT '0' AFTER `time_created`;
UPDATE blocks SET locally_saved=1;

