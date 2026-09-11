<?php
namespace MyOrdersVault\Core;

// Builds a link back to the source email in Gmail. Deliberately uses a
// #search/ link rather than #all/<threadId>: on iOS, Safari intercepts
// mail.google.com links with a "Open in Gmail?" prompt, and neither
// choice honors the #all/<id> fragment — both just land on the inbox.
// #search/ is the one fragment format both Safari and the Gmail app
// reliably resolve to the actual message.
class GmailLink {
    private const FABRICATED_ORDER_NUMBER = '/^(GEN|PP)-[a-f0-9]{10}$/i';

    public static function build(?string $orderNumber, ?string $threadId, ?string $gmailMessageId): ?string
    {
        if (!empty($orderNumber) && !preg_match(self::FABRICATED_ORDER_NUMBER, $orderNumber)) {
            return 'https://mail.google.com/mail/u/0/#search/' . rawurlencode($orderNumber);
        }

        if (!empty($threadId)) {
            return 'https://mail.google.com/mail/u/0/#all/' . rawurlencode($threadId);
        }

        if (!empty($gmailMessageId)) {
            return 'https://mail.google.com/mail/u/0/#all/' . rawurlencode($gmailMessageId);
        }

        return null;
    }
}
