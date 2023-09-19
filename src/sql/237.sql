ALTER TABLE `games` CHANGE `default_betting_mode` `default_betting_mode` ENUM('principal','inflationary') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'principal';
UPDATE games SET default_betting_mode='principal';
UPDATE user_games SET betting_mode='principal';
