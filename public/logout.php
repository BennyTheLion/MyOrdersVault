<?php

require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Url;
use MyOrdersVault\Core\Session;

Session::start();
Session::destroy();

header('Location: ' . Url::base() . '/');
exit;
