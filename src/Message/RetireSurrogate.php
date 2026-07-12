<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A Discourse user was destroyed: unsubscribe their surrogate from the list
 * and delete the surrogate record (PII included).
 */
final readonly class RetireSurrogate
{
    public function __construct(
        public int $discourseUserId,
    ) {
    }
}
