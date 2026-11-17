PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS queued_transactions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	card_id INTEGER NOT NULL,
	provider_transaction_id TEXT NOT NULL,
	change_type TEXT NOT NULL CHECK (change_type IN ('added', 'modified', 'removed')),
	amount NUMERIC DEFAULT NULL, -- All amounts are CAD.
	description TEXT DEFAULT NULL,
	date TEXT DEFAULT NULL,
	pending INTEGER NOT NULL DEFAULT 0 CHECK (pending IN (0, 1)),
	created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (card_id, provider_transaction_id),
	FOREIGN KEY (card_id) REFERENCES cards (id) ON DELETE CASCADE
);
