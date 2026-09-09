<?php
namespace MyOrdersVault\Parsers;

class GenericOrderParser extends BaseParser {
    public function __construct() {
        parent::__construct('כללי');
    }
    
    public function parse($emailContent, $subject) {
        $orderNumber = $this->extractOrderNumber($emailContent, $subject);
        $amount = $this->extractAmount($emailContent);

        // No real order number and no real amount found - this isn't a
        // purchase receipt (e.g. a 2FA code, security alert, or newsletter).
        // Don't fabricate an order just because *some* sender/subject
        // keyword matched; let the caller fall back to confidence scoring.
        if (!$orderNumber && !$amount) {
            return null;
        }

        if (!$orderNumber) {
            // אם אין מספר הזמנה, צור מזהה ייחודי מהנושא
            $orderNumber = 'GEN-' . substr(md5($subject . time()), 0, 10);
        }

        $orderDate = $this->extractDate($emailContent);
        if (!$orderDate) {
            $orderDate = date('Y-m-d H:i:s');
        }

        return $this->createOrderResponse([
            'order_number' => $orderNumber,
            'order_date' => $orderDate,
            'total_amount' => $amount ? $amount['amount'] : null,
            'currency' => $amount ? $amount['currency'] : 'N/A',
            'items' => [],
            'raw_data' => [
                'subject' => $subject,
                'from' => 'generic'
            ]
        ]);
    }
    
    public function supports($emailFrom) {
        // הפארסר הגנרי תומך בכל השולחים
        return true;
    }
    
    private function extractOrderNumber($content, $subject) {
        $patterns = [
            // Requires at least one digit in the captured token, so plain
            // words like "confirmation" or "received" (which follow "order"
            // in unrelated marketing/security emails) don't get mistaken
            // for an order number.
            '/\border[\s:#\-]*([A-Z0-9][A-Z0-9\-]{2,19}[0-9][A-Z0-9\-]*|\d{4,20})\b/i',
            '/\b(?:order|confirmation|booking|receipt|invoice)[\s\-]?#?\s*([0-9]{4,20})\b/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $matches)) {
                return $matches[1];
            }
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}