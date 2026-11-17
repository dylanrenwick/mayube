PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS `transactions` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT CHECK (`id` BETWEEN 0 AND 4294967295),
	`user_id` INTEGER NOT NULL,
	-- NUMERIC does not preserve MySQL DECIMAL(10,2) precision, scale or rounding.
	`amount` NUMERIC NOT NULL,
	`description` TEXT COLLATE NOCASE NOT NULL,
	`date` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`category_id` INTEGER NOT NULL CHECK (typeof(`category_id`) = 'integer' AND `category_id` BETWEEN 0 AND 4294967295),
	`card_id` INTEGER DEFAULT NULL,
	`provider_transaction_id` TEXT DEFAULT NULL,
	UNIQUE (`card_id`, `provider_transaction_id`),
	FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
	FOREIGN KEY (`category_id`) REFERENCES `transaction_categories` (`id`) ON UPDATE CASCADE,
	FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS transactions_user_date ON transactions (user_id, date);
