PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS `category_posts` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT CHECK (`id` BETWEEN 0 AND 4294967295),
	`category_id` INTEGER NOT NULL CHECK (typeof(`category_id`) = 'integer' AND `category_id` BETWEEN 0 AND 4294967295),
	`post_id` INTEGER NOT NULL CHECK (typeof(`post_id`) = 'integer' AND `post_id` BETWEEN 0 AND 4294967295),
	CONSTRAINT `fk_post_id`
		FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
		ON UPDATE RESTRICT,
	CONSTRAINT `fk_category_id`
		FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
		ON UPDATE RESTRICT
);
