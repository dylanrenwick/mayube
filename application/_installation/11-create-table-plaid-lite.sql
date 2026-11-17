PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS plaid_item (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL,
	item_id TEXT NOT NULL UNIQUE,
	access_token TEXT NOT NULL, -- Base64-encoded encrypted credentials.
	public_token_hash TEXT NOT NULL,
	`cursor` TEXT DEFAULT NULL,
	UNIQUE (user_id, public_token_hash),
	FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS plaid_account (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	plaid_item_id INTEGER NOT NULL,
	account_id TEXT NOT NULL,
	name TEXT NOT NULL,
	mask TEXT DEFAULT NULL,
	type TEXT NOT NULL,
	subtype TEXT DEFAULT NULL,
	UNIQUE (plaid_item_id, account_id),
	FOREIGN KEY (plaid_item_id) REFERENCES plaid_item (id) ON DELETE CASCADE
);
