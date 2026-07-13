<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A Discourse user was updated: sync name changes, and re-provision the
 * surrogate if the email changed.
 */
final readonly class SyncSurrogate
{
    public function __construct(
        public int $discourseUserId,
        public string $username,
        public ?string $displayName,
        public ?string $email,
        public bool $staged = false,
    ) {
    }
}
