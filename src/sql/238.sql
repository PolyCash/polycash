ALTER TABLE `games` ADD `order_events_by` VARCHAR(30) NOT NULL DEFAULT 'event_index' AFTER `order_options_by`;
