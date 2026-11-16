PRAGMA foreign_keys = ON;

-- user data
CREATE TABLE IF NOT EXISTS `users` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT CHECK (`id` BETWEEN 0 AND 4294967295), -- auto incrementing id of each user, unique index
	`session_id` TEXT COLLATE NOCASE DEFAULT NULL CHECK (length(`session_id`) <= 48), -- stores session cookie id to prevent session concurrency
	`username` TEXT COLLATE NOCASE NOT NULL CHECK (length(`username`) <= 64), -- user's name, unique
	`password_hash` TEXT COLLATE NOCASE DEFAULT NULL CHECK (length(`password_hash`) <= 255), -- user's password in salted and hashed format
	`email` TEXT COLLATE NOCASE NOT NULL CHECK (length(`email`) <= 254), -- user's email, unique
	`active` INTEGER NOT NULL DEFAULT 0 CHECK (typeof(`active`) = 'integer' AND `active` BETWEEN -128 AND 127), -- user's activation status
	`deleted` INTEGER NOT NULL DEFAULT 0 CHECK (typeof(`deleted`) = 'integer' AND `deleted` BETWEEN -128 AND 127), -- user's deletion status
	`account_type` INTEGER NOT NULL DEFAULT 1 CHECK (typeof(`account_type`) = 'integer' AND `account_type` BETWEEN -128 AND 127), -- user's account type (basic, premium, etc)
	`has_avatar` INTEGER NOT NULL DEFAULT 0 CHECK (typeof(`has_avatar`) = 'integer' AND `has_avatar` BETWEEN -128 AND 127), -- 1 if user has a local avatar, 0 if not
	`remember_me_token` TEXT COLLATE NOCASE DEFAULT NULL CHECK (length(`remember_me_token`) <= 64), -- user's remember-me cookie token
	`creation_timestamp` INTEGER DEFAULT NULL CHECK (`creation_timestamp` IS NULL OR typeof(`creation_timestamp`) = 'integer'), -- timestamp of the creation of user's account
	`suspension_timestamp` INTEGER DEFAULT NULL CHECK (`suspension_timestamp` IS NULL OR typeof(`suspension_timestamp`) = 'integer'), -- timestamp till the end of a user suspension
	`last_login_timestamp` INTEGER DEFAULT NULL CHECK (`last_login_timestamp` IS NULL OR typeof(`last_login_timestamp`) = 'integer'), -- timestamp of user's last login
	`failed_logins` INTEGER NOT NULL DEFAULT 0 CHECK (typeof(`failed_logins`) = 'integer' AND `failed_logins` BETWEEN -128 AND 127), -- user's failed login attempts
	`last_failed_login` INTEGER DEFAULT NULL CHECK (typeof(`last_failed_login`) = 'null' OR (typeof(`last_failed_login`) = 'integer' AND `last_failed_login` BETWEEN -2147483648 AND 2147483647)), -- unix timestamp of last failed login attempt
	`activation_hash` TEXT COLLATE NOCASE DEFAULT NULL CHECK (length(`activation_hash`) <= 40), -- user's email verification hash string
	`password_reset_hash` TEXT COLLATE NOCASE DEFAULT NULL CHECK (length(`password_reset_hash`) <= 40), -- user's password reset code
	`password_reset_timestamp` INTEGER DEFAULT NULL CHECK (`password_reset_timestamp` IS NULL OR typeof(`password_reset_timestamp`) = 'integer'), -- timestamp of the password reset request
	`provider_type` TEXT COLLATE NOCASE,
	CONSTRAINT `username` UNIQUE (`username`),
	CONSTRAINT `email` UNIQUE (`email`)
);

