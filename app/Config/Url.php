<?php

namespace MyOrdersVault\Config;

class Url
{
    public static function base(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        // Local XAMPP
        if (strpos($scriptName, '/my-orders-vault/') !== false) {
            return '/my-orders-vault/public';
        }

        // Hostinger production
        return '/public';
    }
}