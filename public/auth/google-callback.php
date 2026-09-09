<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use MyOrdersVault\Core\Session;
use MyOrdersVault\Services\GoogleAuth;

Session::start();

if (!isset($_GET['code'])) {
    Session::setFlash('error', 'אירעה שגיאה בהתחברות. אנא נסה שוב.');
	header('Location: /my-orders-vault/public/');
    exit;
}

try {
    $googleAuth = new GoogleAuth();
    $userData = $googleAuth->authenticate($_GET['code']);
    Session::set('user_id', $userData['user_id']);
    Session::set('user_name', $userData['name']);
    Session::set('user_email', $userData['email']);
    Session::set('user_picture', $userData['picture']);
	Session::setFlash('success', 'התחברת בהצלחה!');

	} catch (Exception $e) {
		Session::setFlash('error', 'שגיאה: ' . $e->getMessage());
	}
header('Location: /my-orders-vault/public/');
exit;