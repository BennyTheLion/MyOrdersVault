<?php
namespace MyOrdersVault\Parsers;

class OrderParserFactory {
    private static $parsers = [
        AmazonParser::class,
        AliExpressParser::class,
        EbayParser::class,
        TemuParser::class,
        PayPalParser::class,
    ];
    
    public static function create($emailFrom) {
        // מחפש פארסר ספציפי לחנות מוכרת (לפי דומיין השולח)
        foreach (self::$parsers as $parserClass) {
            $parser = new $parserClass();
            if ($parser->supports($emailFrom)) {
                return $parser;
            }
        }

        // לא נמצא פארסר ספציפי - אין שולח מאומת, אז לא מחזירים GenericOrderParser
        // אוטומטית. הקריאה מתבצעת מפורשות ע"י GmailService רק כשרמת הביטחון
        // גבוהה מספיק, כדי שמיילים לא-רלוונטיים לא יעקפו את סף הביטחון.
        return null;
    }
}