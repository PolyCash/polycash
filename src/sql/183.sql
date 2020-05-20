ALTER TABLE `event_types`
  DROP `primary_entity_id`,
  DROP `secondary_entity_id`,
  DROP `block_repetition_length`,
  DROP `entity_id`,
  DROP `short_description`,
  DROP `name`;

DELETE FROM `modules` WHERE module_name='ElectionSim';
