<?php
namespace MyOrdersVault\Parsers;

class PayPalParser extends BaseParser {
    public function __construct() {
        parent::__construct('PayPal');
    }
    
    public function parse($emailContent, $subject) {
        // חיפוש מספר הזמנה
        $orderNumber = $this->extractOrderNumber($emailContent, $subject);
        if (!$orderNumber) {
            $orderNumber = 'PP-' . substr(md5($subject . time()), 0, 10);
        }
        
        $orderDate = $this->extractDate($emailContent);
        $amount = $this->extractAmount($emailContent);
        
        return $this->createOrderResponse([
            'order_number' => $orderNumber,
            'order_date' => $orderDate,
            'total_amount' => $amount ? $amount['amount'] : null,
            'currency' => $amount ? $amount['currency'] : 'USD',
            'items' => [],
            'raw_data' => ['subject' => $subject]
        ]);
    }
    
    public function supports($emailFrom) {
        $domains = ['paypal.com', 'paypal.co.il'];
        foreach ($domains as $domain) {
            if (stripos($emailFrom, $domain) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function extractOrderNumber($content, $subject) {
        $patterns = [
            '/Receipt No\.?:?\s*([A-Z0-9\-]+)/i',
            '/Transaction ID:?\s*([A-Z0-9\-]+)/i',
            '/\b(?:receipt|payment|transaction|invoice)[\s#:]*([A-Z0-9][A-Z0-9\-]{3,}[0-9][A-Z0-9\-]*)/i',
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