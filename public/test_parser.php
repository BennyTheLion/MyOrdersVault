<?php
require_once __DIR__ . '/../vendor/autoload.php';
use MyOrdersVault\Services\GoogleAuth;
use MyOrdersVault\Parsers\OrderParserFactory;
use Google\Client;
use Google\Service\Gmail;

session_start();
if (!isset($_SESSION['user_id'])) {
    die("לא מחובר");
}

$config = require __DIR__ . '/../config/config.php';
$userModel = new MyOrdersVault\Models\User();
$user = $userModel->findById($_SESSION['user_id']);

$client = new Client();
$client->setClientId($config['google']['client_id']);
$client->setClientSecret($config['google']['client_secret']);
$client->setAccessToken($user['access_token']);

$gmail = new Gmail($client);

// שליפת ההודעה האחרונה
$messages = $gmail->users_messages->listUsersMessages('me', ['maxResults' => 5]);
echo "<h2>בדיקת פארסרים להזמנות</h2>";

foreach ($messages->getMessages() as $message) {
    $fullMessage = $gmail->users_messages->get('me', $message->getId(), ['format' => 'full']);
    
    // חילוץ תוכן
    $payload = $fullMessage->getPayload();
    $body = '';
    if ($payload->getBody()->getData()) {
        $body = base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
    }
    
    // חילוץ השולח
    $headers = $payload->getHeaders();
    $from = '';
    foreach ($headers as $header) {
        if ($header->getName() === 'From') {
            $from = $header->getValue();
            break;
        }
    }
    
    echo "<div style='border:1px solid #ccc; margin:10px; padding:10px;'>";
    echo "<strong>Message ID:</strong> " . $message->getId() . "<br>";
    echo "<strong>From:</strong> " . htmlspecialchars($from) . "<br>";
    
    // בדיקת פארסר
    $parser = OrderParserFactory::create($from);
    if ($parser) {
        echo "<span style='color:green'>✅ פארסר נמצא: " . get_class($parser) . "</span><br>";
        
        $orderData = $parser->parse($body, $fullMessage->getSubject());
        if ($orderData) {
            echo "<span style='color:green'>✅ הזמנה זוהתה!</span><br>";
            echo "<pre>";
            print_r($orderData);
            echo "</pre>";
        } else {
            echo "<span style='color:red'>❌ לא זוהתה הזמנה</span><br>";
        }
    } else {
        echo "<span style='color:red'>❌ לא נמצא פארסר מתאים</span><br>";
    }
    
    echo "</div>";
}
?>