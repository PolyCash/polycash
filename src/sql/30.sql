ALTER TABLE `games` CHANGE `event_rule` `event_rule` ENUM('entity_type_option_group','single_event_series','all_pairs') CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(36, '', 'jpg'),
(37, '', 'jpg'),
(38, '', 'jpg'),
(39, '', 'jpg'),
(40, '', 'jpg'),
(41, '', 'jpg'),
(42, '', 'jpg'),
(43, '', 'jpg'),
(44, '', 'jpg'),
(45, '', 'jpg'),
(46, '', 'jpg');
INSERT INTO `entities` (`entity_id`, `entity_type_id`, `default_image_id`, `entity_name`, `first_name`, `last_name`, `electoral_votes`) VALUES
(82, 1, 36, 'Ben Carson', 'Ben', 'Carson', 0),
(83, 1, 37, 'Chris Christie', 'Chris', 'Christie', 0),
(84, 1, 38, 'Elizabeth Warren', 'Elizabeth', 'Warren', 0),
(85, 1, 39, 'Jeb Bush', 'Jeb', 'Bush', 0),
(86, 1, 40, 'Jill Stein', 'Jill', 'Stein', 0),
(87, 1, 21, 'Joe Biden', 'Joe', 'Biden', 0),
(88, 1, 20, 'John Kasich', 'John', 'Kasich', 0),
(89, 1, 41, 'Marco Rubio', 'Marco', 'Rubio', 0),
(90, 1, 42, 'Michael Bloomberg', 'Michael', 'Bloomberg', 0),
(91, 1, 43, 'Mike Huckabee', 'Mike', 'Huckabee', 0),
(92, 1, 22, 'Mitt Romney', 'Mitt', 'Romney', 0),
(93, 1, 23, 'Paul Ryan', 'Paul', 'Ryan', 0),
(94, 1, 44, 'Rand Paul', 'Rand', 'Paul', 0),
(95, 1, 45, 'Sarah Palin', 'Sarah', 'Palin', 0),
(96, 1, 46, 'Scott Walker', 'Scott', 'Walker', 0),
(97, 1, 24, 'Ted Cruz', 'Ted', 'Cruz', 0);
ALTER TABLE `event_types` ADD `primary_entity_id` INT NULL DEFAULT NULL AFTER `entity_id`;
ALTER TABLE `event_types` ADD `secondary_entity_id` INT NULL DEFAULT NULL AFTER `primary_entity_id`;