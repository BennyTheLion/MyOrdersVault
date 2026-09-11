<?php
// One-off migration: encrypts any access_token/refresh_token still stored
// as plaintext from before app/Core/Crypto.php existed. Safe to re-run —
// values already tagged with Crypto's "enc:v1:" prefix are left alone.
// Run once per environment after deploying the token-encryption change:
//   php bin/encrypt-existing-tokens.php

require_once __DIR__ . '/../vendor/autoload.php';

use MyOrdersVault\Config\Database;
use MyOrdersVault\Core\Crypto;

$db = Database::getInstance()->getConnection();

$rows = $db->query('SELECT id, access_token, refresh_token FROM users')->fetchAll();

$updated = 0;
foreach ($rows as $row) {
    $needsAccess = !Crypto::isEncrypted($row['access_token']);
    $needsRefresh = !Crypto::isEncrypted($row['refresh_token']);

    if (!$needsAccess && !$needsRefresh) {
        continue;
    }

    $stmt = $db->prepare('UPDATE users SET access_token = :access_token, refresh_token = :refresh_token WHERE id = :id');
    $stmt->execute([
        'access_token' => $needsAccess ? Crypto::encrypt($row['access_token']) : $row['access_token'],
        'refresh_token' => $needsRefresh ? Crypto::encrypt($row['refresh_token']) : $row['refresh_token'],
        'id' => $row['id'],
    ]);
    $updated++;
}

echo "Encrypted tokens for {$updated} of " . count($rows) . " user(s).\n";
