ALTER TABLE `viewer_identifiers` ADD UNIQUE (`type`, `identifier`);
DROP TABLE `browserstrings`;
DROP TABLE `browsers`;