PRAGMA foreign_keys = ON;

-- Preserve the table name from the original script despite its filename.
CREATE TABLE IF NOT EXISTS `transaction_categories` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT CHECK (`id` BETWEEN 0 AND 4294967295),
	`name` TEXT COLLATE NOCASE NOT NULL
);
