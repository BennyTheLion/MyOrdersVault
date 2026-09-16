<?php
namespace MyOrdersVault\Models;

use MyOrdersVault\Config\Database;
use PDO;

class ContactMessage {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($userId, $email, $name, $subject, $message, $attachmentPath = null) {
        $stmt = $this->db->prepare("
            INSERT INTO contact_messages (user_id, email, name, subject, message, attachment_path)
            VALUES (:user_id, :email, :name, :subject, :message, :attachment_path)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
            'subject' => $subject,
            'message' => $message,
            'attachment_path' => $attachmentPath,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM contact_messages WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function all() {
        $stmt = $this->db->query("
            SELECT * FROM contact_messages ORDER BY FIELD(status, 'new', 'read', 'resolved'), created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE contact_messages SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countNew() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM contact_messages WHERE status = 'new'");
        return (int) $stmt->fetch()['total'];
    }
}
