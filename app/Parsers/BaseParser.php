<?php
namespace MyOrdersVault\Parsers;

abstract class BaseParser implements OrderParserInterface {
    protected $storeName;
    
    public function __construct($storeName) {
        $this->storeName = $storeName;
    }
    
    protected function extractNumber($text, $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    protected function extractDate($text, $pattern = null) {
        if ($pattern === null) {
            $pattern = '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})|(\d{4}-\d{2}-\d{2})/';
        }
        
        if (preg_match($pattern, $text, $matches)) {
            $dateStr = trim($matches[0]);
            $date = date_create($dateStr);
            return $date ? $date->format('Y-m-d H:i:s') : null;
        }
        return null;
    }
    
    protected function extractAmount($text) {
        // Hebrew total/paid labels - amounts here are often a bare integer
        // with no currency symbol and no decimal point (e.g. "סך הכל לתשלום 92").
        if (preg_match('/(?:סך\s*הכל\s*לתשלום|סה"כ|סה״כ|לתשלום|שולם|סכום לתשלום)[\s:]*(?:[\$€£₪]\s*)?(\d+(?:[.,]\d{2})?)/u', $text, $matches)) {
            return ['amount' => floatval(str_replace(',', '', $matches[1])), 'currency' => 'ILS'];
        }

        // Pattern for currency symbols - using Unicode property for currency symbols
        if (preg_match('/(?:total|amount|sum|price)[\s:]*([\$\€\£\¥]?)\s*(\d+(?:[.,]\d{2})?)/i', $text, $matches)) {
            $currency = !empty($matches[1]) ? $matches[1] : '$';
            $amount = floatval(str_replace(',', '', $matches[2]));
            return ['amount' => $amount, 'currency' => $this->getCurrencyCode($currency)];
        }
        
        // Pattern for amount with currency symbol at beginning
        if (preg_match('/([\$\€\£\¥])\s*(\d+(?:[.,]\d{2})?)/i', $text, $matches)) {
            return [
                'amount' => floatval(str_replace(',', '', $matches[2])),
                'currency' => $this->getCurrencyCode($matches[1])
            ];
        }
        
        // Pattern for amount with currency code (USD, EUR, etc.)
        if (preg_match('/(\d+(?:[.,]\d{2})?)\s*(USD|EUR|GBP|JPY|ILS)/i', $text, $matches)) {
            return [
                'amount' => floatval(str_replace(',', '', $matches[1])),
                'currency' => $matches[2]
            ];
        }
        
        return null;
    }
    
    protected function getCurrencyCode($symbol) {
        $currencies = [
            '$' => 'USD',
            '€' => 'EUR',
            '£' => 'GBP',
            '¥' => 'JPY',
            '₪' => 'ILS'
        ];
        return isset($currencies[$symbol]) ? $currencies[$symbol] : 'USD';
    }
    
    protected function createOrderResponse($orderData) {
        return array_merge([
            'store_name' => $this->storeName,
            'order_status' => 'confirmed',
            'items' => [],
            'raw_data' => []
        ], $orderData);
    }
}