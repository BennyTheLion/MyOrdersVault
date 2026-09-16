<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\CSRF;
use MyOrdersVault\Core\Mailer;
use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\ContactMessage;

Session::start();
if (!Session::has('user_id')) {
    header('Location: ' . Url::base() . '/');
    exit;
}

$userId = Session::get('user_id');
$userEmail = Session::get('user_email', '');
$userName = Session::get('user_name', '');
$contactModel = new ContactMessage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        Session::setFlash('error', 'שגיאה: פג תוקף הטופס, נסה שוב.');
        header('Location: ' . Url::base() . '/contact.php');
        exit;
    }

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        Session::setFlash('error', 'נא למלא נושא והודעה.');
        header('Location: ' . Url::base() . '/contact.php');
        exit;
    }

    $attachmentPath = null;
    if (!empty($_FILES['attachment']['name'])) {
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::setFlash('error', 'שגיאה בהעלאת הקובץ, נסה שוב.');
            header('Location: ' . Url::base() . '/contact.php');
            exit;
        }

        $maxBytes = 5 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            Session::setFlash('error', 'הקובץ גדול מדי (מקסימום 5MB).');
            header('Location: ' . Url::base() . '/contact.php');
            exit;
        }

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowedMimes[$mime])) {
            Session::setFlash('error', 'ניתן להעלות תמונות בלבד (JPG, PNG, WEBP, GIF).');
            header('Location: ' . Url::base() . '/contact.php');
            exit;
        }

        $uploadDir = __DIR__ . '/../storage/uploads/contact/';
        $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            Session::setFlash('error', 'שגיאה בשמירת הקובץ, נסה שוב.');
            header('Location: ' . Url::base() . '/contact.php');
            exit;
        }

        $attachmentPath = $filename;
    }

    $contactModel->create($userId, $userEmail, $userName, $subject, $message, $attachmentPath);

    Mailer::notifyAdmins(
        '[My Orders Vault] פנייה חדשה: ' . $subject,
        "מאת: {$userName} <{$userEmail}>\n\n{$message}"
    );

    Session::setFlash('success', '✅ הפנייה נשלחה בהצלחה, נחזור אליך בהקדם.');
    header('Location: ' . Url::base() . '/contact.php');
    exit;
}

require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .contact-wrap { max-width: 640px; margin: 0 auto; padding: 40px 20px 70px; }
    .contact-wrap h1 { font-size: 1.5rem; font-weight: 700; color: var(--gray-800); margin-bottom: 6px; }
    .contact-wrap p.lead-text { color: var(--gray-500); font-size: 0.92rem; margin-bottom: 28px; }
    .contact-card { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .contact-card label { font-weight: 600; font-size: 0.88rem; color: var(--gray-700); margin-bottom: 6px; display: block; }
    .contact-card .form-control { margin-bottom: 18px; }
</style>

<div class="contact-wrap">
    <h1><i class="fas fa-envelope"></i> צור קשר</h1>
    <p class="lead-text">יש לך שאלה, בעיה או הצעה? נשמח לשמוע ולחזור אליך בהקדם.</p>

    <div class="contact-card">
        <form method="POST" action="<?= Url::base() ?>/contact.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::generateToken()) ?>">

            <label for="subject">נושא</label>
            <input type="text" id="subject" name="subject" class="form-control" maxlength="255" required>

            <label for="message">הודעה</label>
            <textarea id="message" name="message" class="form-control" rows="6" required></textarea>

            <label for="attachment">צילום מסך (אופציונלי, עד 5MB)</label>
            <input type="file" id="attachment" name="attachment" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> שלח
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
