<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Verifies the X-Discourse-Event-Signature header: an HMAC-SHA256 of the raw
 * request body with a shared secret, formatted as "sha256=<hex digest>".
 */
final readonly class WebhookSignatureVerifier
{
    private const string SIGNATURE_PREFIX = 'sha256=';

    public function __construct(
        #[Autowire(env: 'DISCOURSE_WEBHOOK_SECRET')]
        #[\SensitiveParameter]
        private string $secret,
    ) {
    }

    public function isValid(string $rawBody, string $signatureHeader): bool
    {
        if ('' === $this->secret) {
            // An unconfigured secret must never mean "accept everything"
            return false;
        }

        if (!str_starts_with($signatureHeader, self::SIGNATURE_PREFIX)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->secret);

        return hash_equals($expected, substr($signatureHeader, \strlen(self::SIGNATURE_PREFIX)));
    }
}
