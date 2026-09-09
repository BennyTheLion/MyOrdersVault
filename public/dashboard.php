<?php
require_once __DIR__ . '/../vendor/autoload.php';
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;

Session::start();
if (!Session::has('user_id')) { 
    header('Location: /public/');
    exit; 
}

$userId = Session::get('user_id');
$orderModel = new Order();
$stats = $orderModel->getStats($userId);
$recentOrders = $orderModel->getRecentOrders($userId, 10);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה - My Orders Vault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            direction: rtl;
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
        }
        .navbar {
            background: #0f172a !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white !important;
        }
        .navbar-brand i {
            margin-left: 10px;
            color: #4f46e5;
        }
        .nav-link {
            color: white !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #4f46e5 !important;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card i {
            font-size: 2.5rem;
            color: #4f46e5;
            margin-bottom: 15px;
        }
        .stats-card h3 {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
            color: #1e293b;
        }
        .stats-card p {
            color: #64748b;
            margin: 0;
            font-size: 0.95rem;
        }
        .stats-card .small-text {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .btn-primary {
            background: #4f46e5;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }
        .footer {
            background: #0f172a;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 50px;
        }
        .recent-title {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #4f46e5;
            display: inline-block;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .table-container th {
            background: #4f46e5;
            color: white;
            padding: 12px 15px;
        }
        .table-container td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        .store-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
            background: #64748b; /* fallback for stores without a dedicated brand color below */
        }
        .store-amazon { background: #FF9900; }
        .store-aliexpress { background: #E62E2E; }
        .store-ebay { background: #0064D2; }
        .store-temu { background: #FF6B35; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <h2 class="mb-4">📊 לוח בקרה</h2>
    
    <!-- Stats Cards -->
    <div class="row mb-5">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-shopping-cart"></i>
                <h3><?php echo number_format($stats['total_orders']); ?></h3>
                <p>סה"כ הזמנות</p>
                <p class="small-text">כל ההזמנות שבוצעו</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-dollar-sign"></i>
                <h3><?php echo number_format($stats['total_spent'], 2); ?> ₪</h3>
                <p>סה"כ הוצאות</p>
                <p class="small-text">סך כל הקניות</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-store"></i>
                <h3><?php echo number_format($stats['unique_stores']); ?></h3>
                <p>חנויות שונות</p>
                <p class="small-text">בהן ביצעת רכישות</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <i class="fas fa-calendar-alt"></i>
                <h3><?php echo date('m/Y'); ?></h3>
                <p>חודש נוכחי</p>
                <p class="small-text">סטטיסטיקות מעודכנות</p>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders Section -->
    <div class="mt-5">
        <h4 class="recent-title">📋 הזמנות אחרונות</h4>
        
        <div class="table-container mt-3 table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>חנות</th>
                        <th>מספר הזמנה</th>
                        <th>תאריך</th>
                        <th>סכום</th>
                        <th>סטטוס</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="fas fa-inbox fa-3x mb-3 d-block text-muted"></i>
                            <p>אין הזמנות להצגה</p>
                            <a href="/public/sync.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-sync-alt"></i> סנכרן עכשיו
                            </a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td>
                                <span class="store-badge store-<?php echo strtolower($order['store_name']); ?>">
                                    <?php echo htmlspecialchars($order['store_name']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                            <td><?php echo number_format($order['total_amount'], 2); ?> <?php echo htmlspecialchars($order['currency']); ?></td>
                            <td><span class="status-badge"><?php echo htmlspecialchars($order['order_status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center mt-4">
            <a href="/public/orders.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> צפה בכל ההזמנות
            </a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
