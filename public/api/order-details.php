<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\GmailLink;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;
use MyOrdersVault\Models\User;
use MyOrdersVault\Services\ExchangeRateService;

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

$preferredCurrency = (new User())->getPreferredCurrency($userId);
$displayAmount = $order['total_amount'];
$displayCurrency = $order['currency'];
$originalAmountNote = null;
if ($preferredCurrency && $preferredCurrency !== $order['currency']) {
    $converted = (new ExchangeRateService())->convert($order['total_amount'], $order['currency'], $preferredCurrency);
    if ($converted !== null) {
        $displayAmount = $converted;
        $displayCurrency = $preferredCurrency;
        $originalAmountNote = number_format($order['total_amount'], 2) . ' ' . $order['currency'];
    }
}

$html = '<div class="row">
    <div class="col-md-6">
        <p><strong>🏪 חנות:</strong> ' . htmlspecialchars($order['store_name']) . '</p>
        <p><strong>🔢 מספר הזמנה:</strong> ' . htmlspecialchars($order['order_number']) .
            ($isFabricatedNumber ? ' <span class="text-muted">(לא נמצא מספר הזמנה במייל - זהו מזהה פנימי)</span>' : '') . '</p>
        <p><strong>📅 תאריך:</strong> ' . date('d/m/Y', strtotime($order['order_date'])) . '</p>
    </div>
    <div class="col-md-6">
        <p><strong>💰 סכום כולל:</strong> <span id="orderAmountDisplay-' . $order['id'] . '">' . number_format($displayAmount, 2) . ' ' . htmlspecialchars($displayCurrency) . '</span>' .
            ($originalAmountNote ? ' <span class="text-muted" style="font-size: 0.8rem;">(' . htmlspecialchars($originalAmountNote) . ')</span>' : '') . '</p>
        <p><strong>📊 סטטוס:</strong> <span class="badge bg-success">' . htmlspecialchars($order['order_status']) . '</span></p>
    </div>
</div>';

$html .= '<hr>
<div id="disputeSection-' . $order['id'] . '" data-csrf="' . htmlspecialchars(CSRF::generateToken()) . '">
    <button type="button" class="btn btn-sm btn-outline-warning" onclick="document.getElementById(\'disputeForm-' . $order['id'] . '\').style.display = \'block\'; this.style.display = \'none\';">
        <i class="fas fa-triangle-exclamation"></i> הסכום שגוי? דווח על מחלוקת
    </button>
    <div id="disputeForm-' . $order['id'] . '" style="display:none; margin-top: 12px; padding: 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;">
        <p class="mb-2" style="font-size: 0.85rem; color: #92400e;">
            תקן את הסכום לערך הנכון. ההזמנה שלך תתעדכן מיד, ונשלח דיווח לצוות כדי לבדוק את המקרה.
        </p>
        <div class="mb-2">
            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">הסכום הנכון (ב-' . htmlspecialchars($order['currency']) . ', המטבע המקורי של ההזמנה)</label>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="correctedAmount-' . $order['id'] . '" value="' . htmlspecialchars($order['total_amount']) . '">
        </div>
        <div class="mb-2">
            <label class="form-label" style="font-size: 0.85rem; font-weight: 600;">הסבר (אופציונלי)</label>
            <textarea class="form-control form-control-sm" id="correctionReason-' . $order['id'] . '" rows="2"></textarea>
        </div>
        <div id="correctionMsg-' . $order['id'] . '" class="small mb-2"></div>
        <button type="button" class="btn btn-sm btn-warning" onclick="submitCorrection(' . $order['id'] . ')">
            <i class="fas fa-check"></i> שמור תיקון
        </button>
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

    if (!$isFabricatedNumber) {
        $html .= '
    <button type="button" class="btn btn-sm btn-outline-secondary" data-order-number="' . htmlspecialchars($order['order_number']) . '" onclick="copyOrderNumber(this)">
        <i class="fas fa-copy"></i> העתק מספר הזמנה
    </button>
    <p class="text-muted mt-2" style="font-size: 0.8rem;">
        <i class="fas fa-circle-info"></i>
        בטלפון נייד הקישור לרוב יפתח את אפליקציית Gmail עם תוצאות חיפוש ולא את המייל הספציפי ישירות —
        זו מגבלה של אפליקציית Gmail עצמה. אם זה קורה: העתק את מספר ההזמנה בכפתור למעלה,
        פתח את Gmail, הדבק אותו בשורת החיפוש ולחץ חיפוש כדי למצוא את המייל.
        במחשב הקישור אמור לפתוח את המייל הרלוונטי ישירות.
    </p>';
    }
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