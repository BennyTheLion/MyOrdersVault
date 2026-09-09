<?php
require_once __DIR__ .  '/../vendor/autoload.php';

use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\Order;

Session::start();

$isLoggedIn = Session::has('user_id');
$userName = Session::get('user_name', 'Guest');
$userPicture = Session::get('user_picture', '');

$orderModel = new Order();
$stats = $isLoggedIn ? $orderModel->getStats(Session::get('user_id')) : ['total_orders' => 0, 'total_spent' => 0, 'unique_stores' => 0];
$recentOrders = $isLoggedIn ? $orderModel->getRecentOrders(Session::get('user_id'), 5) : [];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Mobile-first: base rules below target small screens; min-width
       queries at the bottom scale things up for tablets/desktop. The
       whole logged-out view (hero + about) is one flex column sized to
       the viewport so it fits on one screen without scrolling - only the
       footer (outside .home-view) is expected to require a scroll. */
    .home-view { min-height: calc(100vh - 64px); display: flex; flex-direction: column; justify-content: center; gap: 6px; }

    .hero-section { position: relative; text-align: center; padding: 14px 16px 6px; overflow: hidden; }
    .hero-section::before,
    .hero-section::after { content: ''; position: absolute; border-radius: 50%; z-index: 0; pointer-events: none; }
    .hero-section::before { top: -70px; right: -70px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(59,130,246,0.12), transparent 70%); }
    .hero-section::after { bottom: -80px; left: -80px; width: 230px; height: 230px; background: radial-gradient(circle, rgba(16,185,129,0.10), transparent 70%); }
    .hero-content { position: relative; z-index: 1; }
    .hero-illustration { margin: 0 auto 8px; filter: drop-shadow(0 8px 16px rgba(17,24,39,0.08)); }
    .hero-illustration svg { width: 90px; height: auto; }
    .hero-section h1 { font-size: 1.4rem; font-weight: 700; color: var(--gray-800); margin-bottom: 6px; }
    .hero-section h1 .accent { color: var(--primary); }
    .hero-section p { color: var(--gray-500); font-size: 0.85rem; margin-bottom: 14px; }
    .hero-section .btn-google { padding: 8px 20px; font-size: 0.85rem; }

    /* "What is this / how to connect" section for logged-out visitors */
    .about-section { padding: 6px 16px 10px; max-width: 900px; margin: 0 auto; width: 100%; }
    .about-lead { text-align: center; color: var(--gray-600); font-size: 0.78rem; line-height: 1.5; max-width: 680px; margin: 0 auto 16px; }
    .steps-title { text-align: center; font-size: 1rem; font-weight: 700; color: var(--gray-800); margin-bottom: 12px; }
    .step-card { text-align: center; height: 100%; }
    .step-number { width: 28px; height: 28px; margin: 0 auto 6px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
    .step-card h4 { font-size: 0.8rem; font-weight: 600; color: var(--gray-800); margin-bottom: 3px; }
    .step-card p { color: var(--gray-500); font-size: 0.72rem; line-height: 1.4; margin: 0; }
    .privacy-note { display: flex; align-items: center; gap: 8px; justify-content: center; margin-top: 14px; color: var(--gray-500); font-size: 0.68rem; text-align: center; }
    .privacy-note i { color: var(--success); }

    @media (min-width: 768px) {
        .home-view { gap: 10px; }
        .hero-section { padding: 24px 20px 10px; }
        .hero-illustration svg { width: 120px; }
        .hero-section h1 { font-size: 2rem; margin-bottom: 10px; }
        .hero-section p { font-size: 0.95rem; margin-bottom: 20px; }
        .about-lead { font-size: 0.9rem; margin-bottom: 22px; }
        .steps-title { font-size: 1.15rem; margin-bottom: 16px; }
        .step-number { width: 34px; height: 34px; font-size: 0.9rem; }
        .step-card h4 { font-size: 0.88rem; }
        .step-card p { font-size: 0.78rem; }
        .privacy-note { font-size: 0.75rem; margin-top: 20px; }
    }
</style>

<?php if ($flash = Session::getFlash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert"><?php echo htmlspecialchars($flash); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container <?php echo $isLoggedIn ? 'mt-4' : ''; ?>">
    <?php if ($isLoggedIn): ?>
        <div class="row mt-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stats-value"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stats-label">סה"כ הזמנות</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stats-value"><?php echo number_format($stats['total_spent'], 2); ?> ₪</div>
                    <div class="stats-label">סה"כ הוצאות</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-icon"><i class="fas fa-store"></i></div>
                    <div class="stats-value"><?php echo number_format($stats['unique_stores']); ?></div>
                    <div class="stats-label">חנויות</div>
                </div>
            </div>
        </div>
        <div class="table-container mt-4 table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>חנות</th><th>מספר הזמנה</th><th>תאריך</th><th>סכום</th></tr></thead>
                <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr><td><?php echo htmlspecialchars($order['store_name']); ?></td><td><?php echo htmlspecialchars($order['order_number']); ?></td><td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td><td><?php echo number_format($order['total_amount'], 2); ?> <?php echo htmlspecialchars($order['currency']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="home-view">
        <div class="hero-section">
            <div class="hero-content">
                <div class="hero-illustration">
                    <svg viewBox="0 0 170 200" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 8 H155 V175 L145 185 L135 175 L125 185 L115 175 L105 185 L95 175 L85 185 L75 175 L65 185 L55 175 L45 185 L35 175 L25 185 L15 175 Z"
                              fill="#ffffff" stroke="#e5e7eb" stroke-width="2"/>
                        <rect x="32" y="30" width="106" height="10" rx="5" fill="#3b82f6"/>
                        <rect x="32" y="52" width="80" height="6" rx="3" fill="#e5e7eb"/>
                        <rect x="32" y="66" width="92" height="6" rx="3" fill="#e5e7eb"/>
                        <rect x="32" y="80" width="60" height="6" rx="3" fill="#e5e7eb"/>
                        <line x1="32" y1="100" x2="138" y2="100" stroke="#e5e7eb" stroke-width="2" stroke-dasharray="4 4"/>
                        <rect x="32" y="114" width="46" height="9" rx="4" fill="#9ca3af"/>
                        <rect x="96" y="112" width="42" height="13" rx="4" fill="#111827"/>
                        <circle cx="138" cy="38" r="22" fill="#10b981"/>
                        <path d="M128 38 l6.5 6.5 L150 30" stroke="#ffffff" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1>ברוכים הבאים ל<span class="accent">-My Orders Vault</span></h1>
                <p>מרכז את כל ההזמנות האונליין שלך במקום אחד</p>
                <a href="auth/google.php" class="btn-google btn-lg"><i class="fab fa-google"></i> התחל עכשיו</a>
            </div>
        </div>

        <div class="about-section">
            <p class="about-lead">
                My Orders Vault סורק את תיבת ה-Gmail שלך ומאתר אוטומטית מיילים של אישורי הזמנה,
                חשבוניות וקבלות מחנויות שונות - ומרכז את כולם בטבלה אחת נוחה, עם סכום, תאריך ומספר הזמנה לכל רכישה,
                כדי שלא תצטרך לחפש אותם ידנית בין המיילים.
            </p>

            <h3 class="steps-title">איך מתחברים?</h3>
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>מתחברים עם Google</h4>
                        <p>לוחצים על "התחל עכשיו" ומתחברים עם חשבון ה-Gmail שלך.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>מאשרים גישה לקריאה בלבד</h4>
                        <p>האפליקציה מבקשת הרשאת קריאה בלבד לתיבת המייל - היא לעולם לא שולחת או מוחקת מיילים.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>סנכרון אוטומטי</h4>
                        <p>לוחצים "סנכרן" ותוך שניות ההזמנות שלך מכל החנויות מופיעות בלוח הבקרה.</p>
                    </div>
                </div>
            </div>

            <div class="privacy-note">
                <i class="fas fa-lock"></i>
                <span>גישת קריאה בלבד (read-only) - האפליקציה לא שולחת, מוחקת או משנה מיילים בחשבון שלך.</span>
            </div>
        </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
