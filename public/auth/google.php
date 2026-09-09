<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use MyOrdersVault\Services\GoogleAuth;

$googleAuth = new GoogleAuth();
header('Location: ' . $googleAuth->getAuthUrl());
exit;