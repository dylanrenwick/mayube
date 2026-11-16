PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS `sheets` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT CHECK (`id` BETWEEN 0 AND 4294967295),
	`title` TEXT COLLATE NOCASE NOT NULL,
	`created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- SQLite has no column-level ON UPDATE CURRENT_TIMESTAMP clause.
-- Update the timestamp when another value changes, unless a different
-- updated_at value was supplied. See SQLITE-NOTES.md for the remaining edge case.
CREATE TRIGGER IF NOT EXISTS `sheets_updated_at`
AFTER UPDATE ON `sheets`
FOR EACH ROW
WHEN NEW.`updated_at` IS OLD.`updated_at`
	AND (NEW.`id` IS NOT OLD.`id`
		OR NEW.`title` COLLATE BINARY IS NOT OLD.`title` COLLATE BINARY
		OR NEW.`created_at` IS NOT OLD.`created_at`)
BEGIN
	UPDATE `sheets` SET `updated_at` = CURRENT_TIMESTAMP WHERE `id` = NEW.`id`;
END;
