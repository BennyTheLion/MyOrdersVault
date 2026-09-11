-- The uk_gmail_message_id key was unique across all users instead of being
-- scoped per user, so the upsert in GmailMessage::save()/markProcessed()
-- could collide across different users' mailboxes. Run this once against
-- an existing database that was created before this fix.
ALTER TABLE `gmail_messages` DROP INDEX `uk_gmail_message_id`;
ALTER TABLE `gmail_messages` ADD UNIQUE KEY `uk_user_gmail_message_id` (`user_id`, `gmail_message_id`);
