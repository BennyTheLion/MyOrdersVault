<?php
session_start();
header('Content-Type: application/json');

// בדוק אם יש סנכרון פעיל
$syncFile = __DIR__ . '/../../storage/sync_active.lock';

if (file_exists($syncFile) && (time() - filemtime($syncFile)) < 60) {
    echo json_encode(['syncing' => true]);
} else {
    if (file_exists($syncFile)) {
        unlink($syncFile);
    }
    echo json_encode(['syncing' => false]);
}