<?php
namespace MyOrdersVault\Models;

use MyOrdersVault\Config\Database;
use PDO;

class GmailMessage {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function isProcessed($userId, $gmailMessageId) {
        $stmt = $this->db->prepare("
            SELECT id FROM gmail_messages 
            WHERE user_id = :user_id AND gmail_message_id = :gmail_message_id AND is_processed = 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'gmail_message_id' => $gmailMessageId
        ]);
        return $stmt->fetch() !== false;
    }
    
    public function save($userId, $data) {
		
		$debugData = print_r($data, true);
		$debugLog = "========================================\n";
		$debugLog .= "[" . date('Y-m-d H:i:s') . "] SAVE FUNCTION CALLED\n";
		$debugLog .= "User ID: {$userId}\n";
		$debugLog .= "Data: {$debugData}\n";
	
		file_put_contents(__DIR__ . '/../../storage/logs/GmailMessage_debug.log', $debugLog, FILE_APPEND);
        
		$stmt = $this->db->prepare("
            INSERT INTO gmail_messages (user_id, gmail_message_id, thread_id, subject, from_email, from_name, received_at)
            VALUES (:user_id, :gmail_message_id, :thread_id, :subject, :from_email, :from_name, :received_at)
            ON DUPLICATE KEY UPDATE id = id
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'gmail_message_id' => $data['gmail_message_id'],
            'thread_id' => isset($data['thread_id']) ? $data['thread_id'] : null,
            'subject' => isset($data['subject']) ? $data['subject'] : null,
            'from_email' => isset($data['from_email']) ? $data['from_email'] : null,
            'from_name' => isset($data['from_name']) ? $data['from_name'] : null,
            'received_at' => isset($data['received_at']) ? $data['received_at'] : date('Y-m-d H:i:s')
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function markProcessed($userId, $gmailMessageId) {
        // upsert - a skipped message (low confidence / no parser) never went
        // through save(), so there may be no row yet to UPDATE. Without this,
        // isProcessed() would keep returning false and the same junk message
        // gets re-fetched and re-evaluated on every single sync run.
        $stmt = $this->db->prepare("
            INSERT INTO gmail_messages (user_id, gmail_message_id, is_processed, processed_at)
            VALUES (:user_id, :gmail_message_id, 1, NOW())
            ON DUPLICATE KEY UPDATE is_processed = 1, processed_at = NOW()
        ");
        return $stmt->execute([
            'user_id' => $userId,
            'gmail_message_id' => $gmailMessageId
        ]);
    }
}