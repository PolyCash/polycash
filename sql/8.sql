INSERT INTO voting_option_groups SET option_group_id=5, option_name='empire', option_name_plural='empires', description='World War 2 belligerents';
INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(25, '', 'png'),
(26, '', 'png'),
(27, '', 'png'),
(28, '', 'png'),
(29, '', 'png');
INSERT INTO `voting_options` (`voting_option_id`, `option_group_id`, `name`, `voting_character`, `default_image_id`) VALUES
(36, 5, 'Italy', '1', 25),
(37, 5, 'China', '2', 26),
(38, 5, 'Germany', '3', 27),
(39, 5, 'Japan', '4', 28),
(40, 5, 'Soviet Union', '5', 29),
(41, 5, 'United Kingdom', '6', 12),
(42, 5, 'United States', '7', 2);
