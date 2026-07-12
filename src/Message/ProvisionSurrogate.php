<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A Discourse user was created: create their surrogate and subscribe it to the list.
 */
final readonly class ProvisionSurrogate
{
    public function __construct(
        public int $discourseUserId,
        public string $username,
        public ?string $displayName,
        public ?string $email,
    ) {
    }
}
