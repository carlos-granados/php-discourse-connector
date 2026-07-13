<?php

declare(strict_types=1);

namespace App\Inbound;

/**
 * An email received in the catch-all mailbox of the surrogate domain,
 * reduced to the fields the connector needs.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $replyTo,
        public string $subject,
        public string $raw,
    ) {
    }
}
