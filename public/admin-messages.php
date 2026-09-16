<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\ContactMessage;

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

$contactModel = new ContactMessage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'שגיאה: פג תוקף הטופס, נסה שוב.');
        header('Location: ' . Url::base() . '/admin-messages.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $msg = $contactModel->find($id);
        if ($msg && $contactModel->delete($id) && !empty($msg['attachment_path'])) {
            $attachmentFile = __DIR__ . '/../storage/uploads/contact/' . basename($msg['attachment_path']);
            if (is_file($attachmentFile)) {
                unlink($attachmentFile);
            }
        }
    } else {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['new', 'read', 'resolved'], true)) {
            $contactModel->updateStatus($id, $status);
        }
    }

    header('Location: ' . Url::base() . '/admin-messages.php');
    exit;
}

$messages = $contactModel->all();

$orders_css = 'includes/orders.css';
require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .msg-wrap { max-width: 900px; margin: 0 auto; padding: 40px 20px 70px; }
    .msg-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .msg-card.status-new { border-right: 4px solid #f59e0b; }
    .msg-card.status-read { border-right: 4px solid #3b82f6; }
    .msg-card.status-resolved { border-right: 4px solid #10b981; opacity: 0.75; }
    .msg-meta { font-size: 0.82rem; color: var(--gray-500); margin-bottom: 8px; }
    .msg-subject { font-weight: 700; font-size: 1.02rem; color: var(--gray-800); margin-bottom: 6px; }
    .msg-body { white-space: pre-wrap; font-size: 0.9rem; color: var(--gray-700); margin-bottom: 14px; }
    .msg-actions form { display: inline-block; margin-inline-end: 6px; }
</style>

<div class="msg-wrap">
    <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 20px;">
        <i class="fas fa-inbox"></i> פניות משתמשים
    </h1>

    <?php if (empty($messages)): ?>
        <p class="text-muted">אין פניות כרגע.</p>
    <?php else: ?>
        <?php foreach ($messages as $msg): ?>
            <div class="msg-card status-<?= htmlspecialchars($msg['status']) ?>">
                <div class="msg-meta">
                    <?= htmlspecialchars($msg['name'] ?: $msg['email']) ?> &lt;<?= htmlspecialchars($msg['email']) ?>&gt;
                    · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($msg['created_at']))) ?>
                </div>
                <div class="msg-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                <div class="msg-body"><?= htmlspecialchars($msg['message']) ?></div>
                <?php if (!empty($msg['attachment_path'])): ?>
                    <div class="msg-attachment" style="margin-bottom: 14px;">
                        <a href="<?= Url::base() ?>/admin-attachment.php?id=<?= (int) $msg['id'] ?>" target="_blank" rel="noopener">
                            <img src="<?= Url::base() ?>/admin-attachment.php?id=<?= (int) $msg['id'] ?>" alt="צילום מסך מצורף" style="max-width: 220px; max-height: 220px; border-radius: 8px; border: 1px solid #e5e7eb;">
                        </a>
                    </div>
                <?php endif; ?>
                <div class="msg-actions">
                    <?php foreach (['new' => 'חדש', 'read' => 'נקרא', 'resolved' => 'טופל'] as $value => $label): ?>
                        <?php if ($msg['status'] !== $value): ?>
                            <form method="POST" action="<?= Url::base() ?>/admin-messages.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::generateToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                                <input type="hidden" name="status" value="<?= $value ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $label ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <form method="POST" action="<?= Url::base() ?>/admin-messages.php" onsubmit="return confirm('למחוק את הפנייה הזו לצמיתות?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::generateToken()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> מחק</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
