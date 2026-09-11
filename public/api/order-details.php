<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use MyOrdersVault\Core\GmailLink;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;

header('Content-Type: application/json');
Session::start();

if (!Session::has('user_id') || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$orderId = intval($_GET['id']);
$userId = Session::get('user_id');
$orderModel = new Order();
$order = $orderModel->getById($orderId, $userId);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$rawData = json_decode($order['raw_data'] ?? '', true);
$emailSubject = $rawData['original_data']['subject'] ?? null;
$isFabricatedNumber = (bool) preg_match('/^(GEN|PP)-[a-f0-9]{10}$/i', $order['order_number']);

$html = '<div class="row">
    <div class="col-md-6">
        <p><strong>🏪 חנות:</strong> ' . htmlspecialchars($order['store_name']) . '</p>
        <p><strong>🔢 מספר הזמנה:</strong> ' . htmlspecialchars($order['order_number']) .
            ($isFabricatedNumber ? ' <span class="text-muted">(לא נמצא מספר הזמנה במייל - זהו מזהה פנימי)</span>' : '') . '</p>
        <p><strong>📅 תאריך:</strong> ' . date('d/m/Y', strtotime($order['order_date'])) . '</p>
    </div>
    <div class="col-md-6">
        <p><strong>💰 סכום כולל:</strong> ' . number_format($order['total_amount'], 2) . ' ' . htmlspecialchars($order['currency']) . '</p>
        <p><strong>📊 סטטוס:</strong> <span class="badge bg-success">' . htmlspecialchars($order['order_status']) . '</span></p>
    </div>
</div>';

$html .= '<hr><h6>📧 פרטי המייל</h6><div class="row">
    <div class="col-md-6">
        <p><strong>נושא:</strong> ' . htmlspecialchars($emailSubject ?? '—') . '</p>
        <p><strong>שולח:</strong> ' . htmlspecialchars($order['email'] ?? '—') . '</p>
    </div>
    <div class="col-md-6">';

$gmailUrl = GmailLink::build($order['order_number'], $order['thread_id'] ?? null, $order['gmail_message_id'] ?? null);
if ($gmailUrl) {
    $html .= '<a href="' . htmlspecialchars($gmailUrl) . '" target="_blank" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-envelope-open-text"></i> פתח את המייל המקורי בג׳ימייל
    </a>';
} else {
    $html .= '<span class="text-muted">אין קישור למייל המקורי</span>';
}
$html .= '</div></div>';

if (!empty($order['items'])) {
    $html .= '<hr>
    <h6>📦 מוצרים בהזמנה:</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light">
                <tr>
                    <th>מוצר</th>
                    <th>כמות</th>
                    <th>מחיר יחידה</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($order['items'] as $item) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($item['product_name']) . '</td>
                    <td class="text-center">' . $item['quantity'] . '</td>
                    <td class="text-end">' . number_format($item['unit_price'], 2) . ' ₪</td>
                  </tr>';
    }
    $html .= '</tbody>
        </table>
    </div>';
}

echo json_encode(['success' => true, 'html' => $html]);