-- Lets a user fix a wrong order amount themselves (public/api/order-correction.php).
-- The correction is logged here (with a snapshot of what the parser produced)
-- so admins can spot a store whose parser keeps getting the amount wrong,
-- instead of only ever seeing the already-corrected order. Also mirrored to
-- database/schema.sql.
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
