PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS `transactions` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT CHECK (`id` BETWEEN 0 AND 4294967295),
	-- NUMERIC does not preserve MySQL DECIMAL(10,2) precision, scale or rounding.
	`amount` NUMERIC NOT NULL,
	`description` TEXT COLLATE NOCASE NOT NULL,
	`date` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`sheet_id` INTEGER NOT NULL CHECK (typeof(`sheet_id`) = 'integer' AND `sheet_id` BETWEEN 0 AND 4294967295),
	`category_id` INTEGER NOT NULL CHECK (typeof(`category_id`) = 'integer' AND `category_id` BETWEEN 0 AND 4294967295),
	FOREIGN KEY (`sheet_id`) REFERENCES `sheets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	FOREIGN KEY (`category_id`) REFERENCES `transaction_categories` (`id`) ON UPDATE CASCADE
);
