<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\Mailer;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;
use MyOrdersVault\Models\OrderCorrection;

header('Content-Type: application/json');
Session::start();

if (!Session::has('user_id')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'פג תוקף הטופס, נסה שוב.']);
    exit;
}

$userId = Session::get('user_id');
$orderId = (int) ($_POST['order_id'] ?? 0);
$correctedAmount = filter_var($_POST['corrected_amount'] ?? '', FILTER_VALIDATE_FLOAT);
$reason = trim($_POST['reason'] ?? '');

if ($orderId <= 0 || $correctedAmount === false || $correctedAmount < 0) {
    echo json_encode(['success' => false, 'error' => 'נא להזין סכום תקין.']);
    exit;
}

$orderModel = new Order();
$order = $orderModel->getById($orderId, $userId);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'ההזמנה לא נמצאה.']);
    exit;
}

if (!$orderModel->updateAmount($orderId, $userId, $correctedAmount)) {
    echo json_encode(['success' => false, 'error' => 'שגיאה בעדכון הסכום, נסה שוב.']);
    exit;
}

$correctionModel = new OrderCorrection();
$correctionModel->create(
    $orderId,
    $userId,
    $order['store_name'],
    $order['total_amount'],
    $correctedAmount,
    $reason !== '' ? $reason : null,
    json_decode($order['raw_data'] ?? '', true)
);

Mailer::notifyAdmins(
    '[My Orders Vault] מחלוקת על סכום הזמנה: ' . $order['store_name'] . ' #' . $order['order_number'],
    "משתמש תיקן סכום הזמנה.\n\n" .
    "חנות: {$order['store_name']}\n" .
    "מספר הזמנה: {$order['order_number']}\n" .
    "סכום מקורי: {$order['total_amount']} {$order['currency']}\n" .
    "סכום מתוקן: {$correctedAmount} {$order['currency']}\n" .
    "סיבה: " . ($reason !== '' ? $reason : '—') . "\n"
);

echo json_encode([
    'success' => true,
    'corrected_amount' => number_format($correctedAmount, 2),
]);
