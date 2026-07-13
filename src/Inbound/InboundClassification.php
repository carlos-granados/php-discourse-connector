<?php

declare(strict_types=1);

namespace App\Inbound;

use App\Enum\InboundEmailKind;

final readonly class InboundClassification
{
    public function __construct(
        public InboundEmailKind $kind,
        /** The ezmlm cookie address a confirmation reply must be sent to. */
        public ?string $confirmationAddress = null,
    ) {
    }
}
