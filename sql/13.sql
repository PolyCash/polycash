UPDATE `option_groups` SET `description` = 'Clinton and Trump' WHERE `group_id` = 1;
UPDATE `option_groups` SET `description` = 'Clinton, Sanders & Trump' WHERE `group_id` = 2;
UPDATE `option_groups` SET `description` = 'Clinton, Johnson & Trump' WHERE `group_id` = 4;
UPDATE `option_group_memberships` SET `entity_id` = '62' WHERE `membership_id` = 5;