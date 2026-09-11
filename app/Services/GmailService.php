<?php
namespace MyOrdersVault\Services;

use MyOrdersVault\Parsers\OrderParserFactory;

class GmailService {

    private $client;
    private $gmail;
    private $userModel;
    private $gmailMessageModel;
    private $userId;

    public function __construct($userId) {
        $this->userId = $userId;
        $this->userModel = new \MyOrdersVault\Models\User();
        $this->gmailMessageModel = new \MyOrdersVault\Models\GmailMessage();

        $userTokens = $this->userModel->getTokens($userId);

        if (!$userTokens) {
            throw new \Exception('User tokens not found');
        }

        $configPath = __DIR__ . '/../../../config/config.php';
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/../../config/config.php';
        }
        $config = require $configPath;

        $this->client = new \Google\Client();
        $this->client->setClientId($config['google']['client_id']);
        $this->client->setClientSecret($config['google']['client_secret']);

        if (strtotime($userTokens['token_expires_at']) < time()) {
            $auth = new \MyOrdersVault\Services\GoogleAuth();
            $newTokens = $auth->refreshToken($userTokens['refresh_token']);
            $this->userModel->updateTokens(
                $userId,
                $newTokens['access_token'],
                $userTokens['refresh_token'],
                $newTokens['expires_at']
            );
            $this->client->setAccessToken($newTokens['access_token']);
        } else {
            $this->client->setAccessToken($userTokens['access_token']);
        }

        $this->gmail = new \Google\Service\Gmail($this->client);
    }

    // ─────────────────────────────────────────────
    // LAYER 1 — GMAIL QUERY BUILDER
    // ─────────────────────────────────────────────

    private function buildSearchQuery(?int $afterTimestamp = null): string
    {
        // --- English order/invoice keywords ---
        $englishSubjectKeywords = [
            'order confirmation', 'order receipt', 'purchase confirmation',
            'payment confirmation', 'payment receipt', 'invoice', 'receipt',
            'your order', 'order summary', 'order details', 'order shipped',
            'order dispatched', 'shipment confirmation', 'delivery confirmation',
            'thank you for your purchase', 'thank you for your order',
            'billing confirmation', 'transaction receipt', 'your purchase',
        ];

        // --- Hebrew order/invoice keywords ---
        $hebrewSubjectKeywords = [
            'אישור הזמנה', 'אישור רכישה', 'חשבונית', 'קבלה',
            'פרטי הזמנה', 'סיכום הזמנה', 'ההזמנה שלך', 'הרכישה שלך',
            'תודה על הזמנתך', 'תודה על רכישתך', 'אישור תשלום',
            'אישור עסקה', 'חשבונית מס', 'חשבונית עסקה',
            'הזמנתך התקבלה', 'הרכישה התקבלה', 'מסמך עסקה',
            'פרטי עסקה', 'אסמכתא', 'מספר הזמנה',
        ];

        // --- Known sender patterns ---
        $senderPatterns = [
            'from:noreply@amazon', 'from:order@amazon',
            'from:shipment-tracking@amazon', 'from:auto-confirm@amazon',
            'from:orders@aliexpress', 'from:noreply@aliexpress',
            'from:ebay@ebay', 'from:noreply@ebay',
            'from:service@paypal', 'from:service@intl.paypal.com',
            'from:receipts@', 'from:orders@', 'from:billing@',
            'from:invoice@', 'from:noreply@', 'from:no-reply@',
            'from:confirmation@', 'from:support@',
            'from:online@ksp.co.il', 'from:noreply@ivory.co.il',
            'from:orders@bug.co.il', 'from:noreply@zap.co.il',
            'from:noreply@superspharm.co.il', 'from:noreply@shufersal.co.il',
            'from:noreply@rami-levy.co.il', 'from:orders@terminalx.com',
            'from:noreply@adika.com', 'from:noreply@golf.co.il',
            'from:noreply@factory54.co.il', 'from:orders@shein.com',
            'from:noreply@Castro.co.il', 'from:noreply@renuar.co.il',
            'from:noreply@HOmydesign.com', 'from:noreply@ace.co.il',
            'from:noreply@ikea.com', 'from:info@terminalx.com',
        ];

        // Build subject keyword OR group
        $subjectParts = [];
        foreach ($englishSubjectKeywords as $kw) {
            $subjectParts[] = 'subject:"' . $kw . '"';
        }
        foreach ($hebrewSubjectKeywords as $kw) {
            $subjectParts[] = 'subject:"' . $kw . '"';
        }

        // Build sender OR group
        $senderParts = implode(' OR ', $senderPatterns);

        // Combine: (subject keywords) OR (known senders)
        $subjectQuery = '(' . implode(' OR ', $subjectParts) . ')';
        $senderQuery  = '(' . $senderParts . ')';

        $query = "({$subjectQuery} OR {$senderQuery})";

        // Incremental sync — only ask Gmail for messages received since the
        // last successful sync, instead of re-scanning the whole mailbox
        // every time (that's what made syncing slow).
        if ($afterTimestamp !== null) {
            $query .= " after:{$afterTimestamp}";
        }

        return $query;
    }

    // ─────────────────────────────────────────────
    // LAYER 2 — SENDER SIGNAL SCORING
    // ─────────────────────────────────────────────

    private function scoreSender(string $fromEmail): int
    {
        $score = 0;
        $email = strtolower($fromEmail);

        $tier1Domains = [
            'amazon.com', 'amazon.co.uk', 'amazon.de', 'amazon.co.jp',
            'aliexpress.com', 'ebay.com', 'ebay.co.uk',
            'paypal.com', 'intl.paypal.com',
            'shein.com', 'asos.com', 'etsy.com',
            'ksp.co.il', 'ivory.co.il', 'bug.co.il', 'zap.co.il',
            'shufersal.co.il', 'rami-levy.co.il', 'superspharm.co.il',
            'terminalx.com', 'adika.com', 'golf.co.il', 'castro.co.il',
            'renuar.co.il', 'ace.co.il', 'ikea.com', 'factory54.co.il',
            'box.co.il',
        ];
        foreach ($tier1Domains as $domain) {
            if (str_contains($email, $domain)) return 40;
        }

        $tier2Locals = ['orders@', 'billing@', 'invoice@', 'receipt@', 'confirmation@', 'purchase@'];
        foreach ($tier2Locals as $local) {
            if (str_contains($email, $local)) $score = max($score, 30);
        }

        $tier3Locals = ['noreply@', 'no-reply@', 'donotreply@', 'support@', 'info@', 'service@'];
        foreach ($tier3Locals as $local) {
            if (str_contains($email, $local)) $score = max($score, 20);
        }

        return $score;
    }

    // ─────────────────────────────────────────────
    // LAYER 3 — CONTENT CONFIDENCE SCORING
    // ─────────────────────────────────────────────

    private function scoreContent(string $subject, string $body): array
    {
        $score    = 0;
        $signals  = [];
        $combined = strtolower($subject . ' ' . $body);

        // Order number patterns
        $orderPatterns = [
            '/\b(order|invoice|receipt|confirmation|ref|reference)\s*[:#\-]?\s*\d{4,}/i',
            '/\b(#|no\.?|num\.?|מספר|הזמנה|אסמכתא)\s*\d{4,}/u',
            '/\b[A-Z]{2,5}[-_]\d{4,}/i',
            '/\bINV[-_]?\d+/i', '/\bORD[-_]?\d+/i', '/\b\d{6,12}\b/',
        ];
        foreach ($orderPatterns as $pattern) {
            if (preg_match($pattern, $subject . ' ' . $body)) {
                $score += 25;
                $signals[] = 'order_number_pattern';
                break;
            }
        }

        // Amount patterns
        $amountPatterns = [
            '/[\$€£₪]\s*\d+[\.,]\d{2}/', '/\d+[\.,]\d{2}\s*[\$€£₪]/',
            '/\b(total|amount|subtotal|grand total|סה"כ|סך הכל|לתשלום|סכום)\s*:?\s*[\$€£₪]?\s*\d+/iu',
            '/\b(₪|ILS|USD|EUR|GBP)\s*\d+/i',
        ];
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                $score += 20;
                $signals[] = 'amount_found';
                break;
            }
        }

        // English purchase phrases
        $englishPhrases = [
            'thank you for your order', 'thank you for your purchase',
            'order confirmation', 'purchase confirmation', 'payment confirmation',
            'order has been placed', 'order has been received', 'order is confirmed',
            'your invoice', 'payment received', 'billing summary', 'order summary',
            'shipment confirmation', 'your receipt', 'transaction id',
        ];
        foreach ($englishPhrases as $phrase) {
            if (str_contains($combined, $phrase)) {
                $score += 15;
                $signals[] = 'english_purchase_phrase';
                break;
            }
        }

        // Hebrew purchase phrases
        $hebrewPhrases = [
            'תודה על הזמנתך', 'תודה על רכישתך', 'אישור הזמנה',
            'אישור רכישה', 'אישור תשלום', 'פרטי הזמנה',
            'ההזמנה שלך', 'הרכישה שלך', 'חשבונית מס',
            'הזמנתך התקבלה', 'הרכישה התקבלה', 'מספר הזמנה',
            'אסמכתא', 'לתשלום', 'חשבונית עסקה',
        ];
        $subjectBodyUtf8 = $subject . ' ' . $body;
        foreach ($hebrewPhrases as $phrase) {
            if (mb_strpos($subjectBodyUtf8, $phrase) !== false) {
                $score += 15;
                $signals[] = 'hebrew_purchase_phrase';
                break;
            }
        }

        // Item/product list signals
        $itemPatterns = [
            '/\b(qty|quantity|item|product|sku|כמות|פריט|מוצר)\b/iu',
            '/<tr[^>]*>.*?<td[^>]*>.*?<\/td>/is',
            '/\d+\s*[xX×]\s*[\$€£₪]?\d+/',
        ];
        foreach ($itemPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                $score += 10;
                $signals[] = 'item_list_found';
                break;
            }
        }

        // Shipping signals
        $shippingPatterns = [
            '/\b(shipping|delivery|dispatch|tracking|courier|משלוח|מעקב|שליח|מספר מעקב)\b/iu',
            '/\b(estimated delivery|expected delivery|arrives by|יגיע עד)\b/iu',
        ];
        foreach ($shippingPatterns as $pattern) {
            if (preg_match($pattern, $combined)) {
                $score += 10;
                $signals[] = 'shipping_signal';
                break;
            }
        }

        // Subject line boost
        $subjectBoost = ['order', 'invoice', 'receipt', 'purchase', 'confirmation', 'payment', 'billing', 'transaction', 'הזמנה', 'חשבונית', 'קבלה', 'רכישה', 'אישור', 'תשלום'];
        $subjectLower = mb_strtolower($subject);
        foreach ($subjectBoost as $kw) {
            if (mb_strpos($subjectLower, $kw) !== false) {
                $score += 10;
                $signals[] = 'subject_keyword';
                break;
            }
        }

        return ['score' => min($score, 100), 'signals' => array_unique($signals)];
    }

    // ─────────────────────────────────────────────
    // COMBINED CONFIDENCE
    // ─────────────────────────────────────────────

    private function calculateConfidence(string $fromEmail, string $subject, string $body): array
    {
        $senderScore   = $this->scoreSender($fromEmail);
        $contentResult = $this->scoreContent($subject, $body);
        $contentScore  = $contentResult['score'];

        $combined = (int) round(($senderScore / 40 * 35) + ($contentScore / 100 * 65));
        $combined = min($combined, 100);

        return [
            'confidence' => $combined,
            'sender_score'  => $senderScore,
            'content_score' => $contentScore,
            'signals'       => $contentResult['signals'],
        ];
    }

    // ─────────────────────────────────────────────
    // ORDER NUMBER VALIDATION
    // ─────────────────────────────────────────────

    private function validateOrderNumber(?string $orderNumber): array
    {
        if (empty($orderNumber)) {
            return ['is_valid' => false, 'cleaned' => null, 'type' => 'none', 'reason' => 'No order number provided'];
        }

        $original = $orderNumber;
        $cleaned = trim($orderNumber);
        $cleaned = preg_replace('/^(order|inv|ord|ref|#|no\.?|num\.?|number:?)\s*/i', '', $cleaned);
        $cleaned = preg_replace('/^(הזמנה|חשבונית|אסמכתא|מס\'?)\s*/ui', '', $cleaned);

        $patterns = [
            'amazon' => '/^[0-9]{3}-[0-9]{7}-[0-9]{7}$/',
            'paypal' => '/^[A-Z0-9]{17}$/',
            'ebay' => '/^[0-9]{2}-[0-9]{5}-[0-9]{5}$/',
            'standard_digits' => '/^\d{6,20}$/',
            'alphanumeric' => '/^[A-Z0-9\-]{6,25}$/i',
            'short_digits' => '/^\d{4,5}$/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $cleaned)) {
                return ['is_valid' => true, 'cleaned' => $cleaned, 'type' => $type, 'original' => $original, 'reason' => "Matches {$type} format"];
            }
        }

        if (strlen($cleaned) >= 6) {
            return ['is_valid' => true, 'cleaned' => $cleaned, 'type' => 'unknown', 'original' => $original, 'reason' => 'Weak validation - length >= 6'];
        }

        return ['is_valid' => false, 'cleaned' => $cleaned, 'type' => 'invalid', 'original' => $original, 'reason' => 'Does not match any known pattern and length < 6'];
    }

    // ─────────────────────────────────────────────
    // EXTRACT CUSTOMER EMAIL
    // ─────────────────────────────────────────────

    private function extractCustomerEmail(string $body, string $fromEmail): ?string
    {
        // רשימת דפוסים מורחבת לחיפוש מייל
        $patterns = [
            '/\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/',
            '/customer[:\s]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
            '/email[:\s]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
            '/buyer[:\s]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
            '/account[:\s]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
            '/to[:\s]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
        ];

        $foundEmails = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $email) {
                    // בדוק שזה לא מייל של noreply
                    if (stripos($email, 'noreply') === false &&
                        stripos($email, 'no-reply') === false &&
                        stripos($email, 'donotreply') === false) {
                        $foundEmails[] = $email;
                    }
                }
            }
        }

        // הסר כפילויות
        $foundEmails = array_unique($foundEmails);

        // אם יש מיילים, החזר את הראשון (הכי סביר)
        if (!empty($foundEmails)) {
            return $foundEmails[0];
        }

        return null;
    }

    // ─────────────────────────────────────────────
    // EXTRACT EMAIL DATA FROM MESSAGE
    // ─────────────────────────────────────────────

    private function extractEmailData($fullMessage): array
    {
        $payload = $fullMessage->getPayload();
        $parts = $payload->getParts();
        $body = '';

        if ($payload->getBody()->getData()) {
            $body = base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
        } elseif ($parts) {
            foreach ($parts as $part) {
                if ($part->getMimeType() === 'text/plain' && $part->getBody()->getData()) {
                    $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    break;
                } elseif ($part->getMimeType() === 'text/html' && $part->getBody()->getData()) {
                    $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    break;
                }
            }
        }

        $headers = $payload->getHeaders();
        $from = '';
        $subject = '';
        $fromName = '';

        foreach ($headers as $header) {
            if ($header->getName() === 'From') {
                $from = $header->getValue();
                if (preg_match('/(.*)<(.+?)>/', $from, $matches)) {
                    $fromName = trim($matches[1]);
                    $from = $matches[2];
                } else {
                    $fromName = $from;
                }
            }
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }
        }

        return ['body' => $this->htmlToText($body), 'subject' => $subject, 'from' => $from, 'from_name' => $fromName];
    }

    // Many receipts put a label and its value in separate table cells
    // (e.g. "<td>סך הכל לתשלום</td><td>92</td>"). Raw HTML tags between
    // them defeat every whitespace-based regex, so normalize to plain
    // text (tag boundaries -> single space) before any pattern matching.
    private function htmlToText(string $html): string
    {
        if (stripos($html, '<') === false) {
            return $html;
        }
        $text = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = preg_replace('/<[^>]+>/', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    // ─────────────────────────────────────────────
    // BUILD PARTIAL ORDER DATA
    // ─────────────────────────────────────────────

    private function buildPartialOrderData(array $emailData, array $confidenceResult): array
    {
        $body = $emailData['body'];
        $subject = $emailData['subject'];

        // Extract order number
        $orderNumber = null;
        $orderNumPatterns = [
            '/(?:order|invoice|ref|confirmation|הזמנה|אסמכתא)[^\d]*(\d{4,})/iu',
            '/(?:#|no\.?)\s*([A-Z0-9\-]{4,})/i',
            '/\b([A-Z]{2,5}[-_]\d{4,})\b/i',
        ];
        foreach ($orderNumPatterns as $pattern) {
            if (preg_match($pattern, $subject . ' ' . $body, $m)) {
                $orderNumber = $m[1];
                break;
            }
        }

        // Extract amount
        $totalAmount = null;
        $currency = null;
        $amountPatterns = [
            '/([\$€£₪])\s*(\d+[\.,]\d{2})/',
            '/(\d+[\.,]\d{2})\s*([\$€£₪])/',
            '/(?:total|סה"כ|סך הכל|לתשלום)[^\d]*([\d,\.]+)/iu',
        ];
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $body, $m)) {
                if (isset($m[2]) && is_numeric(str_replace([',', '.'], '', $m[2]))) {
                    $currency = $m[1];
                    $totalAmount = (float) str_replace(',', '', $m[2]);
                } elseif (isset($m[1]) && is_numeric(str_replace([',', '.'], '', $m[1]))) {
                    $totalAmount = (float) str_replace(',', '', $m[1]);
                }
                break;
            }
        }

        // Extract store name
        $storeName = $this->extractStoreNameFromEmail($emailData['from']);

        // Extract customer email
        $customerEmail = $this->extractCustomerEmail($body, $emailData['from']);
        if (!$customerEmail) {
            $fromEmail = $emailData['from'];
            if (stripos($fromEmail, 'noreply') === false && stripos($fromEmail, 'no-reply') === false) {
                $customerEmail = $fromEmail;
            }
        }

        // Validate order number
        $orderNumberValidation = null;
        if ($orderNumber) {
            $orderNumberValidation = $this->validateOrderNumber($orderNumber);
            if ($orderNumberValidation['is_valid'] && $orderNumberValidation['cleaned']) {
                $orderNumber = $orderNumberValidation['cleaned'];
            }
        }

        return [
            'order_number' => $orderNumber,
            'order_number_validation' => $orderNumberValidation,
            'customer_email' => $customerEmail,
            'store_name' => $storeName,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'order_date' => date('Y-m-d'),
            'raw_body' => mb_substr($body, 0, 5000),
            'is_partial' => true,
        ];
    }

    private function extractStoreNameFromEmail(string $email): string
    {
        if (preg_match('/@([^>]+)/', $email, $m)) {
            $domain = $m[1];
            $domain = preg_replace('/\.(com|co\.il|net|org|io|co\.uk|de|fr)$/i', '', $domain);
            $parts = preg_split('/[\.\-_]/', $domain);
            return implode(' ', array_map('ucfirst', $parts));
        }
        return $email;
    }

    private function log(string $file, string $message): void
    {
        $line = "[" . date('Y-m-d H:i:s') . "] " . trim($message) . "\n";
        file_put_contents(__DIR__ . '/../../storage/logs/' . $file . '.log', $line, FILE_APPEND);
    }

    // Retries a Gmail API call with exponential backoff when it fails on
    // rateLimitExceeded/userRateLimitExceeded, instead of letting one 403
    // abort the whole backfill.
    private function fetchWithRetry(callable $apiCall, int $maxAttempts = 5)
    {
        $attempt = 0;
        while (true) {
            try {
                return $apiCall();
            } catch (\Exception $e) {
                $attempt++;
                $isRateLimit = stripos($e->getMessage(), 'rateLimitExceeded') !== false
                    || stripos($e->getMessage(), 'userRateLimitExceeded') !== false
                    || stripos($e->getMessage(), 'Quota exceeded') !== false;

                if (!$isRateLimit || $attempt >= $maxAttempts) {
                    throw $e;
                }

                $waitSeconds = min(60, 5 * (2 ** ($attempt - 1))); // 5, 10, 20, 40, 60
                $this->log('sync_debug', "⏳ Rate limited, retrying in {$waitSeconds}s (attempt {$attempt}/{$maxAttempts})");
                sleep($waitSeconds);
            }
        }
    }

    // ─────────────────────────────────────────────
    // MAIN FETCH METHOD
    // ─────────────────────────────────────────────

    public function fetchOrderEmails(int $maxResults = 100, int $maxPages = 50): int
{
    try {
        // Overlap the new window slightly with the previous one (instead of
        // starting exactly where it left off) so a message that arrived in
        // the last few seconds of the prior sync can't be missed; the
        // isProcessed()/unique-key checks make re-seeing it harmless.
        $lastSyncedAt = $this->userModel->getLastSyncedAt($this->userId);
        $syncStartedAt = time();
        $afterTimestamp = $lastSyncedAt !== null ? max(0, $lastSyncedAt - 300) : null;

        $query = $this->buildSearchQuery($afterTimestamp);
        $processedCount = 0;
        $pageToken = null;
        $page = 0;
        $totalSeen = 0;

        $this->log('sync_debug', "[" . date('Y-m-d H:i:s') . "] ===== START SYNC (since " . ($afterTimestamp !== null ? date('Y-m-d H:i:s', $afterTimestamp) : 'beginning') . ") =====\nQuery: {$query}\n");

        do {
            $page++;
            $optParams = [
                'maxResults' => $maxResults,
                'q'          => $query,
                'labelIds'   => ['INBOX'],
            ];
            if ($pageToken) {
                $optParams['pageToken'] = $pageToken;
            }

            $messages = $this->fetchWithRetry(function () use ($optParams) {
                return $this->gmail->users_messages->listUsersMessages('me', $optParams);
            });
            $pageMessages = $messages->getMessages() ?: [];
            $totalSeen += count($pageMessages);

            $this->log('sync_debug', "--- Page {$page}: " . count($pageMessages) . " messages ---\n");

            foreach ($pageMessages as $index => $message) {

                // הוסף לוג של מספר ההודעה בלולאה
                $this->log('sync_debug', "🔄 Processing message " . ($index + 1) . ": " . $message->getId());

                try {
                // בדיקה אם ההודעה כבר טופלה
                if ($this->gmailMessageModel->isProcessed($this->userId, $message->getId())) {
                    $this->log('sync_debug', "⏭️ Already processed, skipping");
                    continue;
                }

                $fullMessage = $this->fetchWithRetry(function () use ($message) {
                    return $this->gmail->users_messages->get('me', $message->getId(), ['format' => 'full']);
                });
                // Gmail per-minute quota is shared across all calls this
                // sync makes; a full mailbox backfill fetches hundreds of
                // messages, so throttle to avoid a 429/403 rateLimitExceeded.
                usleep(250000);
                $emailData = $this->extractEmailData($fullMessage);

                $confidenceResult = $this->calculateConfidence(
                    $emailData['from'],
                    $emailData['subject'],
                    $emailData['body']
                );
                $confidence = $confidenceResult['confidence'];

                $this->log('sync_debug',
                    "📊 Subject: {$emailData['subject']}\n" .
                    "   From: {$emailData['from']}\n" .
                    "   Sender score: {$confidenceResult['sender_score']}/40\n" .
                    "   Content score: {$confidenceResult['content_score']}/100\n" .
                    "   Combined confidence: {$confidence}%\n" .
                    "   Signals: " . implode(', ', $confidenceResult['signals'])
                );

                // 2ף ביטחון מינימלי - 10%
                if ($confidence < 20) {
                    $this->log('sync_debug', "⏭️ Confidence {$confidence}% < 20%, skipping");
                    $this->gmailMessageModel->markProcessed($this->userId, $message->getId());
                    continue;
                }

                $parser = OrderParserFactory::create($emailData['from']);
                $orderData = null;

                if ($parser) {
                    $orderData = $parser->parse($emailData['body'], $emailData['subject']);
                    $this->log('sync_debug',
                        "🔧 Parser: " . get_class($parser) . "\n" .
                        "   Extracted: " . ($orderData ? "YES (#{$orderData['order_number']})" : "NO")
                    );
                } else {
                    $this->log('sync_debug', "🔧 No specific parser found");
                }

                $shouldSave = false;
                $saveReason = '';

                if ($orderData) {
                    // פארסר ספציפי לחנות מוכרת (שולח מאומת) - שומרים ללא תלות בביטחון
                    $shouldSave = true;
                    $saveReason = "parser_success_confidence_{$confidence}";
                } elseif ($confidence >= 70) {
                    // אין שולח מאומת - שומרים רק כשרמת הביטחון גבוהה מספיק,
                    // ומנסים קודם את הפארסר הגנרי לחילוץ מדויק יותר.
                    $shouldSave = true;
                    $genericParser = new \MyOrdersVault\Parsers\GenericOrderParser();
                    $orderData = $genericParser->parse($emailData['body'], $emailData['subject']);
                    if ($orderData) {
                        // הפארסר הגנרי לא מכיר את השולח - קובעים את שם החנות
                        // מהדומיין של המייל בפועל, במקום "כללי".
                        $orderData['store_name'] = $this->extractStoreNameFromEmail($emailData['from']);
                        $saveReason = "generic_parser_high_confidence_{$confidence}";
                    } else {
                        $saveReason = "no_parser_high_confidence_{$confidence}";
                        $orderData = $this->buildPartialOrderData($emailData, $confidenceResult);
                    }
                }

                if (!$shouldSave) {
                    $this->log('sync_debug', "⏭️ Confidence {$confidence}% < 70% and no parser, skipping");
                    $this->gmailMessageModel->markProcessed($this->userId, $message->getId());
                    continue;
                }

                // בדיקה נוספת - האם יש נתונים לשמור?
                if (empty($orderData) || (empty($orderData['order_number']) && empty($orderData['store_name']))) {
                    $this->log('sync_debug', "⚠️ Order data is empty! Cannot save.");
                    $this->gmailMessageModel->markProcessed($this->userId, $message->getId());
                    continue;
                }

                // לוג שהזמנה עומדת להישמר
                $this->log('sync_debug', "✅ WILL SAVE: {$emailData['subject']} - Confidence: {$confidence}%");

                // Validate order number if exists
                if (!empty($orderData['order_number'])) {
                    $orderNumberValidation = $this->validateOrderNumber($orderData['order_number']);
                    $orderData['order_number_validation'] = $orderNumberValidation;

                    if (!$orderNumberValidation['is_valid'] && $confidence < 85) {
                        $this->log('sync_debug', "⚠️ Invalid order number: {$orderData['order_number']} - {$orderNumberValidation['reason']}");
                        $orderData['low_quality'] = true;
                    }

                    if ($orderNumberValidation['is_valid'] && $orderNumberValidation['cleaned']) {
                        $orderData['order_number_cleaned'] = $orderNumberValidation['cleaned'];
                        $this->log('sync_debug', "✅ Cleaned order number: {$orderNumberValidation['cleaned']} ({$orderNumberValidation['type']})");
                    }
                }

                // Extract customer email
                $customerEmail = $this->extractCustomerEmail($emailData['body'], $emailData['from']);
                if ($customerEmail) {
                    $orderData['customer_email'] = $customerEmail;
                    $this->log('sync_debug', "📧 Extracted customer email: {$customerEmail}");
                } else {
                    $fromEmail = $emailData['from'];
                    if (stripos($fromEmail, 'noreply') === false && stripos($fromEmail, 'no-reply') === false) {
                        $orderData['customer_email'] = $fromEmail;
                        $this->log('sync_debug', "📧 Using from_email as customer email: {$fromEmail}");
                    }
                }

                $orderData['confidence'] = $confidence;
                $orderData['save_reason'] = $saveReason;
                $orderData['signals'] = $confidenceResult['signals'];

                $messageId = $this->gmailMessageModel->save($this->userId, [
                    'gmail_message_id' => $message->getId(),
                    'thread_id' => $fullMessage->getThreadId(),
                    'subject' => $emailData['subject'],
                    'from_email' => $emailData['from'],
                    'from_name' => $emailData['from_name'],
                    'received_at' => date('Y-m-d H:i:s', $fullMessage->getInternalDate() / 1000),
                ]);

                $orderModel = new \MyOrdersVault\Models\Order();
                $saveResult = $orderModel->save(
                    $this->userId,
                    $orderData,
                    $message->getId(),
                    $fullMessage->getThreadId()
                );

                $this->log('sync_debug',
                    "💾 gmail_messages ID: {$messageId}\n" .
                    "   Order save: " . ($saveResult ? "SUCCESS (ID: {$saveResult})" : "FAILED") . "\n" .
                    "   Reason: {$saveReason}\n" .
                    "   Customer email: " . ($orderData['customer_email'] ?? 'none') . "\n" .
                    "   Order number valid: " . (($orderData['order_number_validation']['is_valid'] ?? false) ? 'YES' : 'NO')
                );

                $this->gmailMessageModel->markProcessed($this->userId, $message->getId());
                $processedCount++;

            } catch (\Exception $e) {
                // טיפול בשגיאה בהודעה בודדת - ממשיכים להודעה הבאה
                $this->log('sync_debug', "❌ ERROR processing message {$message->getId()}: " . $e->getMessage());
                $this->log('sync_debug', "   File: " . $e->getFile() . " Line: " . $e->getLine());

                // מסמנים כ-processed כדי לא לתקוע את הסנכרון
                try {
                    $this->gmailMessageModel->markProcessed($this->userId, $message->getId());
                } catch (\Exception $ignore) {}
                continue;
            }
            }

            $pageToken = $messages->getNextPageToken();
        } while ($pageToken && $page < $maxPages);

        $this->log('sync_debug', "===== END SYNC. Pages: {$page}, Messages seen: {$totalSeen}, Saved: {$processedCount} =====\n");

        // Record when this sync started (not finished) so the next run's
        // overlap window is relative to it — avoids a gap for messages that
        // arrived while this sync was still running.
        $this->userModel->updateLastSyncedAt($this->userId, $syncStartedAt);

        return $processedCount;

    } catch (\Exception $e) {
        $this->log('sync_debug', "❌ FATAL ERROR in fetchOrderEmails: " . $e->getMessage());
        $this->log('sync_debug', "   File: " . $e->getFile() . " Line: " . $e->getLine());
        throw $e;
    }
}
}
