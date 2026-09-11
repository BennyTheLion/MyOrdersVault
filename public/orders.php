<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\GmailLink;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;

Session::start();
if (!Session::has('user_id')) { 
    header('Location: ' . Url::base() . '/');
    exit; 
}

$userId = Session::get('user_id');
$orderModel = new Order();

// סינונים
$filters = [];
if (isset($_GET['store']) && $_GET['store'] !== '') {
    $filters['store'] = $_GET['store'];
}
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $filters['search'] = $_GET['search'];
}
if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
    $filters['date_to'] = $_GET['date_to'];
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$totalOrders = $orderModel->countAll($userId, $filters);
$totalPages = ceil($totalOrders / $limit);
$orders = $orderModel->getAllOrders($userId, $filters, $offset, $limit);
$stores = $orderModel->getUniqueStores($userId);

$orders_css = 'includes/orders.css';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="font-size: 1.5rem; font-weight: 600; color: #1e293b;">📦 ההזמנות שלי</h2>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">🔍 חיפוש</label>
                <input type="text" name="search" class="form-control" placeholder="מספר הזמנה או חנות..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">🏪 חנות</label>
                <select name="store" class="form-select">
                    <option value="">הכל</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo htmlspecialchars($store['store_name']); ?>" <?php echo (isset($filters['store']) && $filters['store'] == $store['store_name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($store['store_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">📅 תאריך מ</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">📅 תאריך עד</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2 align-items-end">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> חפש
                </button>
                <a href="<?= Url::base() ?>/orders.php" class="btn-reset">
                    <i class="fas fa-undo"></i> נקה
                </a>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="orders-table table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>חנות</th>
                    <th>מספר הזמנה</th>
                    <th>תאריך</th>
                    <th>סכום</th>
                    <th>מטבע</th>
                    <th>סטטוס</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>אין הזמנות להצגה</p>
                            <button onclick="startGlobalSync()" class="btn-search" style="background: #3b82f6; padding: 8px 20px;">
                                <i class="fas fa-sync-alt"></i> סנכרן עכשיו
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <span class="store-badge store-<?php echo strtolower($order['store_name']); ?>">
                                    <?php echo htmlspecialchars($order['store_name']); ?>
                                </span>
                            </td>
                            <td><strong style="font-weight: 600;"><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                            <td><?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($order['currency']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                    <?php 
                                        $statusLabels = [
                                            'confirmed' => 'מאושרת',
                                            'pending' => 'ממתינה',
                                            'shipped' => 'נשלחה',
                                            'delivered' => 'התקבלה'
                                        ];
                                        echo $statusLabels[$order['order_status']] ?? $order['order_status'];
                                    ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <button class="btn-view" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i> פרטים
                                </button>
                                <?php $gmailUrl = GmailLink::build($order['order_number'], $order['thread_id'] ?? null, $order['gmail_message_id'] ?? null); ?>
                                <?php if ($gmailUrl): ?>
                                    <a href="<?php echo htmlspecialchars($gmailUrl); ?>"
                                       class="btn-email"
                                       target="_blank"
                                       title="צפה באימייל המקורי">
                                        <i class="fas fa-envelope"></i> אימייל
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">הקודם</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">הבא</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header" style="border-bottom: 1px solid #e9ecef; background: #f8fafc; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title" style="font-weight: 600;">📄 פרטי הזמנה</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderModalBody" style="padding: 24px;">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">טוען...</span>
                    </div>
                    <p class="mt-2 text-muted">טוען פרטי הזמנה...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    async function viewOrder(orderId) {
        const modal = new bootstrap.Modal(document.getElementById('orderModal'));
        const modalBody = document.getElementById('orderModalBody');
        
        modalBody.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">טוען...</span>
                </div>
                <p class="mt-2 text-muted">טוען פרטי הזמנה...</p>
            </div>
        `;
        
        modal.show();
        
        try {
            const response = await fetch(`<?= Url::base() ?>/api/order-details.php?id=${orderId}`);
            const data = await response.json();
            
            if (data.success) {
                modalBody.innerHTML = data.html;
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> ${data.error || 'שגיאה בטעינת פרטי ההזמנה'}
                    </div>
                `;
            }
        } catch (error) {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> שגיאה בתקשורת עם השרת
                </div>
            `;
        }
    }
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

