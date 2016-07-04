ALTER TABLE `game_voting_options` ADD `name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `voting_option_id`;
ALTER TABLE `game_voting_options` ADD `voting_character` VARCHAR(1) NOT NULL DEFAULT '' AFTER `name`;
UPDATE `game_voting_options` gvo JOIN voting_options vo ON gvo.voting_option_id=vo.voting_option_id SET gvo.name=vo.name, gvo.voting_character=vo.address_character;
