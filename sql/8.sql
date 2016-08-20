ALTER TABLE `games` ADD `identifier_first_char` INT NOT NULL DEFAULT '2' AFTER `featured_score`, ADD `identifier_case_sensitive` BOOLEAN NOT NULL DEFAULT TRUE AFTER `identifier_first_char`;
ALTER TABLE `game_types` ADD `identifier_first_char` INT NOT NULL DEFAULT '2' AFTER `featured`, ADD `identifier_case_sensitive` BOOLEAN NOT NULL DEFAULT TRUE AFTER `identifier_first_char`;
UPDATE game_types SET identifier_first_char=1, identifier_case_sensitive=0 WHERE event_type_name='EmpireCoin Classic';
