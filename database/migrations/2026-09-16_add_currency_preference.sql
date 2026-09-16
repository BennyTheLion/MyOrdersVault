-- Lets a user pick a preferred display currency (header dropdown), and caches
-- USD-based exchange rates so orders (stored in whatever currency the email
-- was in) can be converted for display without hitting an external API on
-- every page load. Also mirrored to database/schema.sql.
ALTER TABLE `users` ADD COLUMN `preferred_currency` VARCHAR(3) NULL AFTER `picture`;

CREATE TABLE `exchange_rates` (
    `currency` VARCHAR(3) NOT NULL PRIMARY KEY,
    `rate_to_usd` DECIMAL(18,8) NOT NULL,
    `fetched_at` DATETIME NOT NULL
) ENGINE=InnoDB;
