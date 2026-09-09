<?php
namespace MyOrdersVault\Core;

class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    public static function setFlash($key, $message) {
        $_SESSION['_flash'][$key] = $message;
    }
    
    public static function getFlash($key) {
        $message = isset($_SESSION['_flash'][$key]) ? $_SESSION['_flash'][$key] : null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
    }
}