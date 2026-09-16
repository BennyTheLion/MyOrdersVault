CREATE DATABASE IF NOT EXISTS `my_orders_vault` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `my_orders_vault`;

-- טבלת משתמשים
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `google_id` VARCHAR(255) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `name` VARCHAR(255),
    `picture` TEXT,
    `access_token` TEXT,
    `refresh_token` TEXT,
    `token_expires_at` DATETIME,
    `last_synced_at` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- טבלת הזמנות
CREATE TABLE `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `gmail_message_id` INT UNSIGNED,
    `store_name` VARCHAR(100) NOT NULL,
    `order_number` VARCHAR(255) NOT NULL,
    `order_date` DATETIME,
    `total_amount` DECIMAL(12,2),
    `currency` VARCHAR(3) DEFAULT 'USD',
    `order_status` VARCHAR(50) DEFAULT 'confirmed',
    `raw_data` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY uk_user_order (`user_id`, `store_name`, `order_number`)
) ENGINE=InnoDB;

-- הוסף שדה gmail_message_id אם אין (יש כבר)
-- והוסף שדה thread_id
ALTER TABLE `orders` ADD COLUMN `thread_id` VARCHAR(255) AFTER `gmail_message_id`;
ALTER TABLE `orders` ADD COLUMN `email` VARCHAR(255) NULL AFTER `user_id`;

-- טבלת פריטי הזמנה
CREATE TABLE `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(500) NOT NULL,
    `quantity` INT DEFAULT 1,
    `unit_price` DECIMAL(12,2),
    `total_price` DECIMAL(12,2),
    `sku` VARCHAR(255),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- טבלת הודעות Gmail
CREATE TABLE `gmail_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `gmail_message_id` VARCHAR(255) NOT NULL,
    `thread_id` VARCHAR(255),
    `subject` TEXT,
    `from_email` VARCHAR(255),
    `from_name` VARCHAR(255),
    `received_at` DATETIME,
    `is_processed` TINYINT DEFAULT 0,
    `processed_at` DATETIME,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY uk_user_gmail_message_id (`user_id`, `gmail_message_id`)
) ENGINE=InnoDB;

-- Contact/support form: lets logged-in users send you a message, stored for
-- review in public/admin/messages.php (also mirrored to database/schema.sql).
CREATE TABLE `contact_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255),
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `attachment_path` VARCHAR(255) NULL,
    `status` ENUM('new', 'read', 'resolved') NOT NULL DEFAULT 'new',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Lets a user fix a wrong order amount themselves (public/api/order-correction.php).
-- The correction is logged here (with a snapshot of what the parser produced)
-- so admins can spot a store whose parser keeps getting the amount wrong,
-- instead of only ever seeing the already-corrected order.
CREATE TABLE `order_corrections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `store_name` VARCHAR(100) NOT NULL,
    `original_amount` DECIMAL(12,2),
    `corrected_amount` DECIMAL(12,2) NOT NULL,
    `reason` TEXT,
    `raw_data` JSON,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX idx_store_created (`store_name`, `created_at`)
) ENGINE=InnoDB;
