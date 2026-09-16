<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\User;

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

$allowed = ['USD', 'ILS', 'EUR', 'GBP'];
$currency = strtoupper(trim($_POST['currency'] ?? ''));

// Empty selection means "no preference" — show each order in its own
// original currency again, same as before this feature existed.
if ($currency !== '' && !in_array($currency, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'מטבע לא נתמך.']);
    exit;
}

$userId = Session::get('user_id');
(new User())->setPreferredCurrency($userId, $currency !== '' ? $currency : null);

echo json_encode(['success' => true]);
