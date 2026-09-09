<?php
namespace MyOrdersVault\Models;

use MyOrdersVault\Config\Database;  // 👈 זה התיקון החשוב!
use PDO;

class Order {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function save($userId, $orderData, $gmailMessageId = null, $threadId = null) {
    
		$debugLog = "========================================\n";
		$debugLog .= "[" . date('Y-m-d H:i:s') . "] Order SAVE FUNCTION CALLED\n";
		$debugLog .= "User ID: {$userId}\n";
		$debugLog .= "Email: " . ($orderData['customer_email'] ?? 'NULL') . "\n";
		$debugLog .= "Order Number: " . ($orderData['order_number'] ?? 'NULL') . "\n";
		$debugLog .= "Store Name: " . ($orderData['store_name'] ?? 'NULL') . "\n";
		$debugLog .= "Gmail Message ID: " . ($gmailMessageId ?? 'NULL') . "\n";
		$debugLog .= "Thread ID: " . ($threadId ?? 'NULL') . "\n";
		$debugLog .= "Order Date: " . ($orderData['order_date'] ?? 'NULL') . "\n";
		$debugLog .= "Total Amount: " . ($orderData['total_amount'] ?? 'NULL') . "\n";
		$debugLog .= "Currency: " . ($orderData['currency'] ?? 'NULL') . "\n";
		$debugLog .= "========================================\n";
		
		file_put_contents(__DIR__ . '/../../storage/logs/save_debug.log', $debugLog, FILE_APPEND);

		// בדיקה אם ההזמנה כבר קיימת
		$stmt = $this->db->prepare("
			SELECT id FROM orders 
			WHERE user_id = :user_id AND store_name = :store_name AND order_number = :order_number
		");
		$stmt->execute([
			'user_id' => $userId,
			'store_name' => $orderData['store_name'],
			'order_number' => $orderData['order_number']
		]);
		
		if ($stmt->fetch()) {
			$skipLog = "[" . date('Y-m-d H:i:s') . "] ⏭️ ORDER ALREADY EXISTS - SKIPPED: {$orderData['order_number']}\n";
			file_put_contents(__DIR__ . '/../../storage/logs/save_debug.log', $skipLog, FILE_APPEND);
			return false;
		}
		
		// הכנת ערכים עם ברירות מחדל
		$emailValue = $orderData['customer_email'] ?? $orderData['email'] ?? null;
		$storeName = $orderData['store_name'] ?? 'Unknown';
		$orderNumber = $orderData['order_number'] ?? 'NO_NUMBER_' . time();
		$orderDate = $orderData['order_date'] ?? date('Y-m-d H:i:s');
		$totalAmount = $orderData['total_amount'] ?? 0;
		$currency = $orderData['currency'] ?? 'USD';
		$orderStatus = $orderData['order_status'] ?? 'confirmed';
		
		// שמירת ההזמנה
		$stmt = $this->db->prepare("
			INSERT INTO orders (user_id, email, gmail_message_id, thread_id, store_name, order_number, order_date, 
							   total_amount, currency, order_status, raw_data)
			VALUES (:user_id, :email, :gmail_message_id, :thread_id, :store_name, :order_number, :order_date,
					:total_amount, :currency, :order_status, :raw_data)
		");
		
		$result = $stmt->execute([
			'user_id' => $userId,
			'email' => $emailValue,
			'gmail_message_id' => $gmailMessageId,
			'thread_id' => $threadId,
			'store_name' => $storeName,
			'order_number' => $orderNumber,
			'order_date' => $orderDate,
			'total_amount' => $totalAmount,
			'currency' => $currency,
			'order_status' => $orderStatus,
			'raw_data' => json_encode([
				'analysis' => $orderData['analysis'] ?? [],
				'original_data' => $orderData['raw_data'] ?? []
			])
		]);
		
		error_log("Saving order with email: " . ($emailValue ?? 'NULL'));
		
		if ($result) {
			$orderId = $this->db->lastInsertId();
			$successLog = "[" . date('Y-m-d H:i:s') . "] ✅ ORDER SAVED! ID: {$orderId}, Thread ID: " . ($threadId ?? 'NULL') . "\n";
			file_put_contents(__DIR__ . '/../../storage/logs/save_debug.log', $successLog, FILE_APPEND);
			
			// שמירת פריטי ההזמנה
			if (!empty($orderData['items'])) {
				$stmt = $this->db->prepare("
					INSERT INTO order_items (order_id, product_name, quantity, unit_price, total_price, sku)
					VALUES (:order_id, :product_name, :quantity, :unit_price, :total_price, :sku)
				");
				
				foreach ($orderData['items'] as $item) {
					$stmt->execute([
						'order_id' => $orderId,
						'product_name' => $item['product_name'] ?? 'Unknown Product',
						'quantity' => $item['quantity'] ?? 1,
						'unit_price' => $item['unit_price'] ?? null,
						'total_price' => $item['total_price'] ?? null,
						'sku' => $item['sku'] ?? null
					]);
				}
			}
			
			return $orderId;
		} else {
			$errorLog = "[" . date('Y-m-d H:i:s') . "] ❌ INSERT FAILED!\n";
			file_put_contents(__DIR__ . '/../../storage/logs/save_debug.log', $errorLog, FILE_APPEND);
			return false;
		}
	}
    
    public function getStats($userId) {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_spent,
                COUNT(DISTINCT store_name) as unique_stores
            FROM orders
            WHERE user_id = :user_id AND total_amount IS NOT NULL AND total_amount > 0
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch();
    }

    public function getRecentOrders($userId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT o.*,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
            FROM orders o
            WHERE o.user_id = :user_id AND o.total_amount IS NOT NULL AND o.total_amount > 0
            ORDER BY o.order_date DESC, o.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getAllOrders($userId, $filters = [], $offset = 0, $limit = 20) {
        $sql = "SELECT o.*,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
                FROM orders o
                WHERE o.user_id = :user_id AND o.total_amount IS NOT NULL AND o.total_amount > 0";
        $params = ['user_id' => $userId];

        if (!empty($filters['store'])) {
            $sql .= " AND o.store_name = :store";
            $params['store'] = $filters['store'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (o.order_number LIKE :search OR o.store_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND o.order_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND o.order_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY o.order_date DESC, o.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            if ($key !== 'user_id' && $key !== 'limit' && $key !== 'offset') {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function countAll($userId, $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM orders o WHERE o.user_id = :user_id AND o.total_amount IS NOT NULL AND o.total_amount > 0";
        $params = ['user_id' => $userId];
        
        if (!empty($filters['store'])) {
            $sql .= " AND o.store_name = :store";
            $params['store'] = $filters['store'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (o.order_number LIKE :search OR o.store_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['total'];
    }
    
    public function getById($orderId, $userId) {
        $stmt = $this->db->prepare("
            SELECT o.* FROM orders o
            WHERE o.id = :id AND o.user_id = :user_id
        ");
        $stmt->execute(['id' => $orderId, 'user_id' => $userId]);
        $order = $stmt->fetch();
        
        if ($order) {
            $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
            $stmt->execute(['order_id' => $orderId]);
            $order['items'] = $stmt->fetchAll();
        }
        
        return $order;
    }
    
    public function getUniqueStores($userId) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT store_name FROM orders
            WHERE user_id = :user_id AND total_amount IS NOT NULL AND total_amount > 0
            ORDER BY store_name
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
