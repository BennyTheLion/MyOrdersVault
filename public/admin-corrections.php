<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\OrderCorrection;

Session::start();
if (!Session::has('user_id')) {
    header('Location: ' . Url::base() . '/');
    exit;
}

$configPath = __DIR__ . '/../../config/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config/config.php';
}
$config = require $configPath;
$adminEmails = $config['app']['admin_emails'] ?? [];
$userEmail = Session::get('user_email', '');

if (!in_array($userEmail, $adminEmails, true)) {
    http_response_code(403);
    echo 'גישה נדחתה.';
    exit;
}

$correctionModel = new OrderCorrection();
$corrections = $correctionModel->all();

// Group by store so a repeat offender is obvious at a glance.
$byStore = [];
foreach ($corrections as $c) {
    $byStore[$c['store_name']][] = $c;
}

$orders_css = 'includes/orders.css';
require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .corr-wrap { max-width: 900px; margin: 0 auto; padding: 40px 20px 70px; }
    .corr-store-group { margin-bottom: 28px; }
    .corr-store-title { font-weight: 700; font-size: 1.05rem; color: var(--gray-800); margin-bottom: 10px; }
    .corr-store-title .badge-flag { font-size: 0.75rem; margin-inline-start: 8px; }
    .corr-card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .corr-meta { font-size: 0.82rem; color: var(--gray-500); margin-bottom: 6px; }
    .corr-amounts { font-size: 0.92rem; margin-bottom: 6px; }
    .corr-amounts .old { text-decoration: line-through; color: var(--gray-400); }
    .corr-amounts .new { color: #10b981; font-weight: 700; }
    .corr-reason { font-size: 0.88rem; color: var(--gray-700); white-space: pre-wrap; }
</style>

<div class="corr-wrap">
    <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 6px;">
        <i class="fas fa-triangle-exclamation"></i> מחלוקות סכום הזמנות
    </h1>
    <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 24px;">
        תיקוני סכום שמשתמשים ביצעו בעצמם. חנות עם <?= OrderCorrection::RECENT_THRESHOLD ?>+ תיקונים ב-<?= OrderCorrection::RECENT_DAYS ?> הימים האחרונים
        מסומנת לבדיקת הפרסר, וההזמנות החדשות שלה עוברות אוטומטית לתור "ממתינות לבדיקה" עד שהתיקונים נעצרים.
    </p>

    <?php if (empty($byStore)): ?>
        <p class="text-muted">אין תיקוני סכום כרגע.</p>
    <?php else: ?>
        <?php foreach ($byStore as $storeName => $items): ?>
            <?php $flagged = $correctionModel->isStoreFlagged($storeName); ?>
            <div class="corr-store-group">
                <div class="corr-store-title">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($storeName) ?>
                    <span class="badge bg-secondary"><?= count($items) ?> סה"כ</span>
                    <?php if ($flagged): ?>
                        <span class="badge bg-danger badge-flag">
                            <i class="fas fa-flag"></i> מסומן — יתכן ופרסר החנות זקוק לתיקון
                        </span>
                    <?php endif; ?>
                </div>
                <?php foreach ($items as $c): ?>
                    <div class="corr-card">
                        <div class="corr-meta">
                            הזמנה #<?= htmlspecialchars($c['order_number'] ?? '—') ?>
                            · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['created_at']))) ?>
                        </div>
                        <div class="corr-amounts">
                            <span class="old"><?= number_format((float) $c['original_amount'], 2) ?></span>
                            &larr;
                            <span class="new"><?= number_format((float) $c['corrected_amount'], 2) ?></span>
                        </div>
                        <?php if (!empty($c['reason'])): ?>
                            <div class="corr-reason"><?= htmlspecialchars($c['reason']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
