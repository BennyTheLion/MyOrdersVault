<?php
// הוסף debug ישירות למסך
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\GmailMessage;
use MyOrdersVault\Models\Order;
use MyOrdersVault\Services\GmailService;

Session::start();

echo "<pre>";
echo "=== SYNC DEBUG ===\n";
echo "User logged in: " . (Session::has('user_id') ? "YES" : "NO") . "\n";

if (!Session::has('user_id')) {
    echo "User not logged in!\n";
    header('Location: ' . Url::base() . '/');
    exit;
}

if (!CSRF::verifyToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo "Invalid or missing CSRF token.\n";
    exit;
}

$userId = Session::get('user_id');
echo "User ID: {$userId}\n";

$isFullSync = ($_GET['full'] ?? '') === '1';

$sinceTimestamp = null;
if (!$isFullSync && !empty($_GET['since'])) {
    $sinceDate = $_GET['since'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sinceDate)) {
        $parsed = strtotime($sinceDate . ' 00:00:00');
        if ($parsed !== false && $parsed <= time()) {
            $sinceTimestamp = $parsed;
        }
    }
}

$startTime = microtime(true);
$lockFile = __DIR__ . '/../storage/sync_active.lock';

try {
    echo "Creating lock file...\n";
    file_put_contents($lockFile, time());

    if ($isFullSync) {
        echo "FULL SYNC: wiping non-disputed orders and resetting message markers...\n";
        $orderModel = new Order();
        $deletedCount = $orderModel->deleteAllExceptCorrected($userId);
        (new GmailMessage())->resetProcessed($userId);
        echo "Deleted {$deletedCount} orders (orders with an open correction were kept as-is).\n";
    }

    echo "Creating GmailService...\n";
    $gmailService = new GmailService($userId);

    echo "Calling fetchOrderEmails...\n";
    $processedCount = $gmailService->fetchOrderEmails(100, 50, $isFullSync, $sinceTimestamp);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    echo "SUCCESS! Processed: {$processedCount} orders in {$duration} seconds\n";

    if ($isFullSync) {
        $successMessage = "✅ הסנכרון המלא הושלם! נמצאו ועובדו {$processedCount} הזמנות. ({$duration} שניות)";
    } elseif ($sinceTimestamp !== null) {
        $successMessage = "✅ הסנכרון מתאריך " . date('d/m/Y', $sinceTimestamp) . " הושלם! נמצאו ועובדו {$processedCount} הזמנות. ({$duration} שניות)";
    } else {
        $successMessage = "✅ סנכרן בהצלחה! נמצאו ועובדו {$processedCount} הזמנות חדשות. ({$duration} שניות)";
    }
    Session::setFlash('success', $successMessage);

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
