<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\WebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureVerifierTest extends TestCase
{
    private const string SECRET = 'test-secret';

    private WebhookSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WebhookSignatureVerifier(self::SECRET);
    }

    public function testAcceptsAValidSignature(): void
    {
        $body = '{"user":{"id":42}}';
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        self::assertTrue($this->verifier->isValid($body, $signature));
    }

    public function testRejectsATamperedBody(): void
    {
        $signature = 'sha256='.hash_hmac('sha256', '{"user":{"id":42}}', self::SECRET);

        self::assertFalse($this->verifier->isValid('{"user":{"id":43}}', $signature));
    }

    public function testRejectsASignatureMadeWithAnotherSecret(): void
    {
        $body = '{"user":{"id":42}}';
        $signature = 'sha256='.hash_hmac('sha256', $body, 'other-secret');

        self::assertFalse($this->verifier->isValid($body, $signature));
    }

    public function testRejectsASignatureWithoutTheSha256Prefix(): void
    {
        $body = '{"user":{"id":42}}';
        $signature = hash_hmac('sha256', $body, self::SECRET);

        self::assertFalse($this->verifier->isValid($body, $signature));
    }

    public function testRejectsEverythingWhenTheSecretIsNotConfigured(): void
    {
        $verifier = new WebhookSignatureVerifier('');
        $body = '{"user":{"id":42}}';
        $signature = 'sha256='.hash_hmac('sha256', $body, '');

        self::assertFalse($verifier->isValid($body, $signature));
    }
}
