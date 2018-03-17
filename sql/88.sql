ALTER TABLE `blocks` ADD INDEX (`blockchain_id`, `time_loaded`);
INSERT INTO modules SET module_name='ImageTournament';