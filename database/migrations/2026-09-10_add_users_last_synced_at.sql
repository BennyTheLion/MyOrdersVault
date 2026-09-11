-- users.last_synced_at was added to schema.sql in commit c4682ed4 (incremental
-- Gmail sync) but this migration was missed at the time, so any database
-- created before that commit is missing the column. header.php and
-- GmailService::fetchOrderEmails() both read/write it, and the ALTER was
-- only ever applied by hand — this file documents it for the record and for
-- any other environment that still needs it.
ALTER TABLE `users` ADD COLUMN `last_synced_at` INT UNSIGNED NULL AFTER `token_expires_at`;
