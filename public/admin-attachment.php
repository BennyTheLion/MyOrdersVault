<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Core\Session;
use MyOrdersVault\Models\ContactMessage;

Session::start();
if (!Session::has('user_id')) {
    http_response_code(403);
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
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$msg = (new ContactMessage())->find($id);

if (!$msg || empty($msg['attachment_path'])) {
    http_response_code(404);
    exit;
}

// attachment_path is a server-generated hex filename (see contact.php upload
// handling) — basename() is just defense in depth against path traversal.
$path = __DIR__ . '/../storage/uploads/contact/' . basename($msg['attachment_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
