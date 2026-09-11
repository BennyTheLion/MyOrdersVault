<?php
// header.php - Minimalist Gray Elegant Theme

require_once __DIR__ . '/../../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Models\Order;
use MyOrdersVault\Models\User;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$baseUrl = Url::base();
$csrfToken = $isLoggedIn ? CSRF::generateToken() : null;
$userName = $_SESSION['user_name'] ?? 'אורח';
$userEmail = $_SESSION['user_email'] ?? '';
$userPicture = $_SESSION['user_picture'] ?? '';

$lastSyncedLabel = null;
$pendingReviewCount = 0;
if ($isLoggedIn) {
    $lastSyncedAt = (new User())->getLastSyncedAt($_SESSION['user_id']);
    $lastSyncedLabel = $lastSyncedAt !== null
        ? 'סונכרן לאחרונה: ' . date('d/m/Y H:i', $lastSyncedAt)
        : 'טרם בוצע סנכרון';
    $pendingReviewCount = (new Order())->countPendingReview($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Orders Vault - מרכז ההזמנות האישי שלך</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=SF+Mono&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/header.css">
	<?php if (!empty($orders_css)): ?>
        <link rel="stylesheet" href="<?php echo $orders_css; ?>">
    <?php endif; ?>
	
</head>
<body>

<!-- Flash Messages -->
<?php if (isset($_SESSION['_flash']['success'])): ?>
    <div class="alert alert-success alert-flash alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['_flash']['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['_flash']['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['_flash']['error'])): ?>
    <div class="alert alert-danger alert-flash alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['_flash']['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['_flash']['error']); ?>
<?php endif; ?>

<!-- Sync Global Indicator -->
<div id="syncGlobalIndicator" class="sync-global-indicator" style="display: none;">
    <div class="spinner"></div>
    <span>🔄 מסנכרן הזמנות...</span>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="<?= $baseUrl ?>/">
            <i class="fas fa-box"></i> My Orders Vault
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/dashboard.php">
                            <i class="fas fa-chart-line"></i> לוח בקרה
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/orders.php">
                            <i class="fas fa-shopping-cart"></i> ההזמנות שלי
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/review.php">
                            <i class="fas fa-magnifying-glass"></i> ממתינות לבדיקה
                            <?php if ($pendingReviewCount > 0): ?>
                                <span class="badge rounded-pill bg-warning text-dark"><?= (int) $pendingReviewCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex">
                <?php if ($isLoggedIn): ?>
                    <div class="user-menu">
                        <?php if ($userPicture): ?>
                            <img src="<?php echo htmlspecialchars($userPicture); ?>" class="user-avatar" alt="תמונת משתמש">
                        <?php else: ?>
                            <i class="fas fa-user-circle fa-2x" style="color: var(--gray-400);"></i>
                        <?php endif; ?>
                        <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                        <button id="syncNavButton" onclick="startGlobalSync()" class="btn-sync-nav" title="<?php echo htmlspecialchars($lastSyncedLabel); ?>">
                            <i class="fas fa-sync-alt"></i> <span>סנכרן</span>
                        </button>
                        <a href="<?= $baseUrl ?>/logout.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i> <span>התנתק</span>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/auth/google.php" class="btn-google">
                        <i class="fab fa-google"></i> התחבר עם Google
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // פונקציה גלובלית להתחלת סנכרון
    function startGlobalSync() {
        const indicator = document.getElementById('syncGlobalIndicator');
        const syncBtn = document.getElementById('syncNavButton');
        
        if (indicator) indicator.style.display = 'flex';
        
        if (syncBtn) {
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<div class="spinner" style="width:14px;height:14px;"></div> <span>מסנכרן...</span>';
        }
        
        fetch('<?= $baseUrl ?>/sync.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': '<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES) ?>'
            }
        })
        .then(response => {
            window.location.href = '<?= $baseUrl ?>/orders.php';
        })
        .catch(error => {
            console.error('Sync error:', error);
            if (indicator) indicator.style.display = 'none';
            if (syncBtn) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<i class="fas fa-sync-alt"></i> <span>סנכרן</span>';
            }
            alert('אירעה שגיאה בסנכרון');
        });
    }
    
    // הסתרת הודעות flash לאחר 5 שניות
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-flash');
        alerts.forEach(alert => {
            alert.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
</script>
