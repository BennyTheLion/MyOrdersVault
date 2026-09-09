<?php
// הוסף debug ישירות למסך
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
use MyOrdersVault\Core\Session;
use MyOrdersVault\Services\GmailService;

Session::start();

echo "<pre>";
echo "=== SYNC DEBUG ===\n";
echo "User logged in: " . (Session::has('user_id') ? "YES" : "NO") . "\n";

if (!Session::has('user_id')) { 
    echo "User not logged in!\n";
    header('Location: /my-orders-vault/public/'); 
    exit; 
}

$userId = Session::get('user_id');
echo "User ID: {$userId}\n";

$startTime = microtime(true);
$lockFile = __DIR__ . '/../storage/sync_active.lock';

try {
    echo "Creating lock file...\n";
    file_put_contents($lockFile, time());
    
    echo "Creating GmailService...\n";
    $gmailService = new GmailService($userId);
    
    echo "Calling fetchOrderEmails...\n";
    $processedCount = $gmailService->fetchOrderEmails(100);
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "SUCCESS! Processed: {$processedCount} orders in {$duration} seconds\n";
    
    Session::setFlash('success', "✅ סנכרן בהצלחה! נמצאו ועובדו {$processedCount} הזמנות חדשות. ({$duration} שניות)");
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    Session::setFlash('error', '❌ שגיאה בסנכרון: ' . $e->getMessage());
}

if (file_exists($lockFile)) {
    unlink($lockFile);
    echo "Lock file deleted.\n";
}

echo "=== END ===\n";
echo "</pre>";
?>
// אחרי סנכרון - עבור לדף ההזמנות
// header('Location: /my-orders-vault/public/orders.php');
// exit;