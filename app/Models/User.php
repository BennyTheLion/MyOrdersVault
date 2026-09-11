<?php
namespace MyOrdersVault\Models;

use MyOrdersVault\Config\Database;
use MyOrdersVault\Core\Crypto;

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function findOrCreate($googleUser) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE google_id = :google_id");
        $stmt->execute(['google_id' => $googleUser['id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET name = :name, email = :email, picture = :picture, 
                    access_token = :access_token, refresh_token = :refresh_token, 
                    token_expires_at = :token_expires_at
                WHERE id = :id
            ");
            $stmt->execute([
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'picture' => $googleUser['picture'],
                'access_token' => Crypto::encrypt($googleUser['access_token']),
                'refresh_token' => Crypto::encrypt($googleUser['refresh_token']),
                'token_expires_at' => $googleUser['token_expires_at'],
                'id' => $user['id']
            ]);
            return $user['id'];
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO users (google_id, email, name, picture, access_token, refresh_token, token_expires_at)
                VALUES (:google_id, :email, :name, :picture, :access_token, :refresh_token, :token_expires_at)
            ");
            $stmt->execute([
                'google_id' => $googleUser['id'],
                'email' => $googleUser['email'],
                'name' => $googleUser['name'],
                'picture' => $googleUser['picture'],
                'access_token' => Crypto::encrypt($googleUser['access_token']),
                'refresh_token' => Crypto::encrypt($googleUser['refresh_token']),
                'token_expires_at' => $googleUser['token_expires_at']
            ]);
            return $this->db->lastInsertId();
        }
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    public function updateTokens($userId, $accessToken, $refreshToken, $expiresAt) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET access_token = :access_token, refresh_token = :refresh_token, token_expires_at = :token_expires_at
            WHERE id = :id
        ");
        return $stmt->execute([
            'access_token' => Crypto::encrypt($accessToken),
            'refresh_token' => Crypto::encrypt($refreshToken),
            'token_expires_at' => $expiresAt,
            'id' => $userId
        ]);
    }

    public function getTokens($userId) {
        $stmt = $this->db->prepare("SELECT access_token, refresh_token, token_expires_at FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $row['access_token'] = Crypto::decrypt($row['access_token']);
            $row['refresh_token'] = Crypto::decrypt($row['refresh_token']);
        }
        return $row;
    }

    public function getLastSyncedAt($userId) {
        $stmt = $this->db->prepare("SELECT last_synced_at FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row && $row['last_synced_at'] !== null ? (int) $row['last_synced_at'] : null;
    }

    public function updateLastSyncedAt($userId, $timestamp) {
        $stmt = $this->db->prepare("UPDATE users SET last_synced_at = :last_synced_at WHERE id = :id");
        return $stmt->execute([
            'last_synced_at' => $timestamp,
            'id' => $userId
        ]);
    }
}