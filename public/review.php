<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;

Session::start();
if (!Session::has('user_id')) {
    header('Location: ' . Url::base() . '/');
    exit;
}

$userId = Session::get('user_id');
$orderModel = new Order();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'שגיאה: פג תוקף הטופס, נסה שוב.');
        header('Location: ' . Url::base() . '/review.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'confirm') {
        $confirmed = $orderModel->confirmReview($orderId, $userId, [
            'store_name' => trim($_POST['store_name'] ?? ''),
            'order_number' => trim($_POST['order_number'] ?? ''),
            'total_amount' => is_numeric($_POST['total_amount'] ?? null) ? (float) $_POST['total_amount'] : null,
            'currency' => trim($_POST['currency'] ?? '') ?: 'USD',
        ]);
        Session::setFlash($confirmed ? 'success' : 'error', $confirmed
            ? '✅ ההזמנה אושרה.'
            : '❌ לא ניתן היה לאשר את ההזמנה (ייתכן שכבר טופלה, או שקיימת הזמנה זהה).');
    } elseif ($action === 'discard') {
        $discarded = $orderModel->discardReview($orderId, $userId);
        Session::setFlash($discarded ? 'success' : 'error', $discarded
            ? '🗑️ ההזמנה הוסרה.'
            : '❌ לא ניתן היה להסיר את ההזמנה.');
    }

    header('Location: ' . Url::base() . '/review.php');
    exit;
}

$pendingOrders = $orderModel->getPendingReview($userId);

$orders_css = 'includes/orders.css';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="font-size: 1.5rem; font-weight: 600; color: #1e293b;">🔍 ממתינות לבדיקה</h2>
    </div>

    <?php if (empty($pendingOrders)): ?>
        <div class="orders-table">
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>אין הזמנות שממתינות לבדיקה כרגע</p>
            </div>
        </div>
    <?php else: ?>
        <p class="text-muted mb-3" style="font-size: 0.85rem;">
            המערכת לא הייתה בטוחה מספיק כדי לאשר את ההזמנות האלה אוטומטית. בדוק/י את הפרטים, תקן/י במידת הצורך ואשר/י, או הסר/י אם זו לא הזמנה אמיתית.
        </p>
        <div class="orders-table table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>חנות</th>
                        <th>מספר הזמנה</th>
                        <th>סכום</th>
                        <th>מטבע</th>
                        <th>הקשר</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $order): ?>
                        <?php
                            $rawData = json_decode($order['raw_data'] ?? '', true) ?: [];
                            $confidence = $rawData['analysis']['confidence'] ?? null;
                            $snippet = $rawData['original_data'] ?? null;
                        ?>
                        <?php $formId = 'review-form-' . (int) $order['id']; ?>
                        <tr>
                            <td>
                                <input type="text" form="<?= $formId ?>" name="store_name" class="form-control form-control-sm" value="<?= htmlspecialchars($order['store_name']) ?>">
                            </td>
                            <td>
                                <input type="text" form="<?= $formId ?>" name="order_number" class="form-control form-control-sm" value="<?= htmlspecialchars($order['order_number']) ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" form="<?= $formId ?>" name="total_amount" class="form-control form-control-sm" value="<?= htmlspecialchars($order['total_amount'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" form="<?= $formId ?>" name="currency" class="form-control form-control-sm" style="width: 70px;" value="<?= htmlspecialchars($order['currency'] ?? 'USD') ?>">
                            </td>
                            <td>
                                <span class="review-context" title="<?= htmlspecialchars($snippet ?? '') ?>">
                                    <?= $confidence !== null ? htmlspecialchars($confidence) . '% · ' : '' ?><?= htmlspecialchars(mb_substr($snippet ?? 'אין תוכן', 0, 60)) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <button type="submit" form="<?= $formId ?>" name="action" value="confirm" class="btn-confirm">
                                    <i class="fas fa-check"></i> אשר
                                </button>
                                <button type="submit" form="<?= $formId ?>" name="action" value="discard" class="btn-discard" onclick="return confirm('להסיר את ההזמנה הזו?');">
                                    <i class="fas fa-trash"></i> הסר
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- One hidden form per row (HTML doesn't allow <form> to wrap <tr>/<td>;
             row controls reference their form by id via the form="" attribute). -->
        <?php foreach ($pendingOrders as $order): ?>
            <form id="review-form-<?= (int) $order['id'] ?>" method="POST" action="<?= Url::base() ?>/review.php" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::generateToken()) ?>">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
