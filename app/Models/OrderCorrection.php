<?php
namespace MyOrdersVault\Models;

use MyOrdersVault\Config\Database;

class OrderCorrection {
    // A store with this many corrections within RECENT_DAYS is treated as
    // "still getting it wrong" — see Order::save()'s guardrail check.
    const RECENT_DAYS = 30;
    const RECENT_THRESHOLD = 3;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($orderId, $userId, $storeName, $originalAmount, $correctedAmount, $reason, $rawData = null) {
        $stmt = $this->db->prepare("
            INSERT INTO order_corrections (order_id, user_id, store_name, original_amount, corrected_amount, reason, raw_data)
            VALUES (:order_id, :user_id, :store_name, :original_amount, :corrected_amount, :reason, :raw_data)
        ");
        $stmt->execute([
            'order_id' => $orderId,
            'user_id' => $userId,
            'store_name' => $storeName,
            'original_amount' => $originalAmount,
            'corrected_amount' => $correctedAmount,
            'reason' => $reason,
            'raw_data' => $rawData !== null ? json_encode($rawData) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // Newest first, grouped visually by store in the admin view.
    public function all() {
        $stmt = $this->db->query("
            SELECT c.*, o.order_number
            FROM order_corrections c
            LEFT JOIN orders o ON o.id = c.order_id
            ORDER BY c.store_name, c.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function recentCountByStore($storeName, $days = self::RECENT_DAYS) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total FROM order_corrections
            WHERE store_name = :store_name AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->bindValue('store_name', $storeName);
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    // A store crosses into the guardrail once it has RECENT_THRESHOLD+
    // corrections in the last RECENT_DAYS days — see Order::save().
    public function isStoreFlagged($storeName) {
        return $this->recentCountByStore($storeName) >= self::RECENT_THRESHOLD;
    }

    public function countRecent($days = 7) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total FROM order_corrections
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->bindValue('days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }
}
