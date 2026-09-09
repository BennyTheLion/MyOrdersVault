<?php
namespace MyOrdersVault\Parsers;

class TemuParser extends BaseParser {
    public function __construct() {
        parent::__construct('Temu');
    }
    
    public function parse($emailContent, $subject) {
        $orderNumber = $this->extractOrderNumber($emailContent, $subject);
        if (!$orderNumber) {
            return null;
        }
        
        $orderDate = $this->extractDate($emailContent);
        $amount = $this->extractAmount($emailContent);
        
        return $this->createOrderResponse([
            'order_number' => $orderNumber,
            'order_date' => $orderDate,
            'total_amount' => $amount ? $amount['amount'] : null,
            'currency' => $amount ? $amount['currency'] : 'USD',
            'items' => [],
            'raw_data' => ['subject' => $subject, 'content_sample' => substr($emailContent, 0, 1000)]
        ]);
    }
    
    public function supports($emailFrom) {
        return stripos($emailFrom, 'temu') !== false;
    }
    
    private function extractOrderNumber($content, $subject) {
        if (preg_match('/order[\s-]?#?\s*([A-Z0-9\-]+)/i', $subject, $matches)) {
            return $matches[1];
        }
        
        $patterns = [
            '/order[\s-]?#?\s*([A-Z0-9\-]+)/i',
            '/order number[\s:]+([A-Z0-9\-]+)/i',
            '/temu order[\s-]?#?\s*([A-Z0-9\-]+)/i',
            '/Order #([A-Z0-9\-]+)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}