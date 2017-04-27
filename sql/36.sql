ALTER TABLE `games` ADD `module` VARCHAR(255) NULL DEFAULT NULL AFTER `logo_image_id`;

CREATE TABLE `modules` (
  `module_id` int(11) NOT NULL,
  `module_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `modules`
  ADD PRIMARY KEY (`module_id`),
  ADD UNIQUE KEY `module_name` (`module_name`);

ALTER TABLE `modules`
  MODIFY `module_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `blocks` ADD INDEX (`blockchain_id`, `locally_saved`);