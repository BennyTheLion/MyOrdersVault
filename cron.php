#!/usr/bin/env php
<?php
/**
 * cron.php - סנכרון אוטומטי של הזמנות
 * הגדר ב-crontab: 0 * * * * php /path/to/cron.php
 */

require_once __DIR__ . "/vendor/autoload.php";

use MyOrdersVault\Config\Database;
use MyOrdersVault\Services\GmailService;

echo "[" . date("Y-m-d H:i:s") . "] Starting sync...\n";

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM users WHERE token_expires_at > NOW()");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    $total = 0;
    foreach ($users as $user) {
        try {
            $gmail = new GmailService($user["id"]);
            $processed = $gmail->fetchOrderEmails(50);
            $total += $processed;
            echo "User {$user["id"]}: {$processed} new orders\n";
            sleep(2);
        } catch (Exception $e) {
            echo "Error user {$user["id"]}: " . $e->getMessage() . "\n";
        }
    }
    echo "Total new orders: {$total}\n";
} catch (Exception $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
echo "[" . date("Y-m-d H:i:s") . "] Sync completed.\n";
