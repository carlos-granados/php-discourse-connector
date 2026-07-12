<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Derives the surrogate email address for a Discourse user's real address,
 * e.g. "jane.doe@example.com" -> "jane.doe+example.com.1f9c04@<surrogate domain>".
 *
 * The local part keeps a human-readable form of the real address (@ replaced
 * by +) followed by a short hash of the full address. The hash guarantees
 * uniqueness: the readable prefix alone is ambiguous (real addresses may
 * already contain "+") and may be truncated to fit length limits.
 */
final readonly class SurrogateAddressFactory
{
    private const int HASH_LENGTH = 6;

    /** Maximum length of the local part of an email address (RFC 5321). */
    private const int MAX_LOCAL_PART_LENGTH = 64;

    public function __construct(
        #[Autowire(env: 'SURROGATE_DOMAIN')]
        private string $surrogateDomain,
    ) {
    }

    public function addressFor(string $realEmail): string
    {
        $email = mb_strtolower(trim($realEmail));

        $readable = strtr($email, ['@' => '+']);
        $readable = preg_replace('/[^a-z0-9+._-]+/', '-', $readable) ?? '';
        $readable = preg_replace('/\.{2,}/', '.', $readable) ?? '';

        $hash = substr(hash('sha256', $email), 0, self::HASH_LENGTH);

        $maxReadableLength = self::MAX_LOCAL_PART_LENGTH - self::HASH_LENGTH - 1;
        $readable = trim(substr($readable, 0, $maxReadableLength), '.-');

        return \sprintf('%s.%s@%s', $readable, $hash, $this->surrogateDomain);
    }
}
