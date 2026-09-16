<?php
namespace MyOrdersVault\Core;

// Builds a link back to the source email in Gmail. Deliberately uses a
// #search/ link rather than #all/<threadId>: on iOS, Safari intercepts
// mail.google.com links with a "Open in Gmail?" prompt, and neither
// choice honors the #all/<id> fragment — both just land on the inbox.
// #search/ is the one fragment format both Safari and the Gmail app
// reliably resolve to.
//
// #search/ alone only ever lands on the results *list* though — it never
// auto-opens the message, which is the "arrives but doesn't open" report.
// Gmail also accepts a 3rd path segment after the query: #search/<query>/
// <threadId>, which opens that specific thread directly if it's among the
// results, while still degrading to the same list-only behavior as before
// if Gmail ever stops honoring it. So always append the thread/message id
// when we have one, instead of using search terms alone.
class GmailLink {
    private const FABRICATED_ORDER_NUMBER = '/^(GEN|PP)-[a-f0-9]{10}$/i';

    public static function build(?string $orderNumber, ?string $threadId, ?string $gmailMessageId): ?string
    {
        $targetId = $threadId ?: $gmailMessageId;

        if (!empty($orderNumber) && !preg_match(self::FABRICATED_ORDER_NUMBER, $orderNumber)) {
            $url = 'https://mail.google.com/mail/u/0/#search/' . rawurlencode($orderNumber);
            if (!empty($targetId)) {
                $url .= '/' . rawurlencode($targetId);
            }
            return $url;
        }

        if (!empty($targetId)) {
            return 'https://mail.google.com/mail/u/0/#all/' . rawurlencode($targetId);
        }

        return null;
    }
}
