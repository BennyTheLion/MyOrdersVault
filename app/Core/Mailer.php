<?php
namespace MyOrdersVault\Core;

class Mailer {
    // Best-effort notification via PHP's mail(); failures are logged, never
    // fatal — the contact message is already saved in the DB regardless.
    public static function notifyAdmins(string $subject, string $body): void {
        $configPath = __DIR__ . '/../../config/config.php';
        $config = file_exists($configPath) ? require $configPath : [];
        $admins = $config['app']['admin_emails'] ?? [];

        if (empty($admins)) {
            return;
        }

        $headers = "Content-Type: text/plain; charset=UTF-8\r\nFrom: noreply@myordersvault.local";

        try {
            foreach ($admins as $to) {
                @mail($to, $subject, $body, $headers);
            }
        } catch (\Throwable $e) {
            error_log('[Mailer] Failed to send admin notification: ' . $e->getMessage());
        }
    }
}
