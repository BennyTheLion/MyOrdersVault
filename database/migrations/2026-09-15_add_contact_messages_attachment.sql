-- Optional screenshot attachment for contact messages (public/contact.php).
-- Stores a server-generated filename only; the file itself lives outside the
-- web root in storage/uploads/contact/ and is served through
-- public/admin-attachment.php, gated to admin_emails.
ALTER TABLE `contact_messages` ADD COLUMN `attachment_path` VARCHAR(255) NULL AFTER `message`;
