<?php

declare(strict_types=1);

namespace App\Inbound;

use App\Enum\InboundEmailKind;
use App\Mail\ListAddressResolver;

/**
 * Classifies emails arriving in the catch-all mailbox by matching ezmlm-idx
 * conventions: confirmation requests come from cookie addresses like
 * "internals+sc.<timestamp>.<cookie>@lists.php.net" (sc = subscribe confirm,
 * uc = unsubscribe confirm), welcome/farewell messages announce themselves
 * in the subject.
 */
final readonly class EzmlmConfirmationMatcher
{
    public function __construct(
        private ListAddressResolver $addresses,
    ) {
    }

    public function classify(InboundMessage $message): InboundClassification
    {
        $cookieAddress = $message->replyTo ?? $message->from;

        if ($this->isCookieAddress($cookieAddress, 'sc')) {
            return new InboundClassification(InboundEmailKind::SubscribeConfirmation, $cookieAddress);
        }

        if ($this->isCookieAddress($cookieAddress, 'uc')) {
            return new InboundClassification(InboundEmailKind::UnsubscribeConfirmation, $cookieAddress);
        }

        if (str_starts_with($message->subject, 'WELCOME to '.$this->addresses->listAddress())) {
            return new InboundClassification(InboundEmailKind::Welcome);
        }

        if (str_starts_with($message->subject, 'GOODBYE from '.$this->addresses->listAddress())) {
            return new InboundClassification(InboundEmailKind::Goodbye);
        }

        if ($this->isBounce($message->from)) {
            return new InboundClassification(InboundEmailKind::Bounce);
        }

        return new InboundClassification(InboundEmailKind::Other);
    }

    private function isCookieAddress(string $address, string $command): bool
    {
        $pattern = \sprintf(
            '/^%s\+%s\.[^@]+@%s$/i',
            preg_quote($this->addresses->listLocalPart(), '/'),
            preg_quote($command, '/'),
            preg_quote($this->addresses->listDomain(), '/'),
        );

        return 1 === preg_match($pattern, $address);
    }

    private function isBounce(string $from): bool
    {
        if (str_starts_with(mb_strtolower($from), 'mailer-daemon@')) {
            return true;
        }

        return str_starts_with(
            mb_strtolower($from),
            mb_strtolower($this->addresses->listLocalPart()).'+return-',
        );
    }
}
