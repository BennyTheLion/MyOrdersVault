<?php
namespace MyOrdersVault\Parsers;

class EbayParser extends BaseParser {
    public function __construct() {
        parent::__construct('eBay');
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
        return stripos($emailFrom, 'ebay') !== false;
    }
    
    private function extractOrderNumber($content, $subject) {
        if (preg_match('/order[\s-]?#?\s*([0-9\-]+)/i', $subject, $matches)) {
            return $matches[1];
        }
        
        $patterns = [
            '/order[\s-]?#?\s*([0-9\-]+)/i',
            '/order number[\s:]+([0-9\-]+)/i',
            '/transaction[\s-]?#?\s*([0-9\-]+)/i',
            '/Order #([0-9\-]+)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
}