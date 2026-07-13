<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound;

use App\Enum\InboundEmailKind;
use App\Inbound\EzmlmConfirmationMatcher;
use App\Inbound\InboundMessage;
use App\Mail\ListAddressResolver;
use PHPUnit\Framework\TestCase;

final class EzmlmConfirmationMatcherTest extends TestCase
{
    private EzmlmConfirmationMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new EzmlmConfirmationMatcher(new ListAddressResolver('internals@lists.php.net'));
    }

    public function testMatchesASubscribeConfirmationRequest(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'internals+sc.1770000000.abcdef-jane.doe=example.com@lists.php.net',
            subject: 'confirm subscribe to internals@lists.php.net',
        ));

        self::assertSame(InboundEmailKind::SubscribeConfirmation, $classification->kind);
        self::assertSame('internals+sc.1770000000.abcdef-jane.doe=example.com@lists.php.net', $classification->confirmationAddress);
    }

    public function testMatchesAnUnsubscribeConfirmationRequest(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'internals+uc.1770000000.abcdef@lists.php.net',
            subject: 'confirm unsubscribe from internals@lists.php.net',
        ));

        self::assertSame(InboundEmailKind::UnsubscribeConfirmation, $classification->kind);
    }

    public function testPrefersTheReplyToHeaderForTheCookieAddress(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'internals-help@lists.php.net',
            subject: 'confirm subscribe to internals@lists.php.net',
            replyTo: 'internals+sc.1770000000.abcdef@lists.php.net',
        ));

        self::assertSame(InboundEmailKind::SubscribeConfirmation, $classification->kind);
        self::assertSame('internals+sc.1770000000.abcdef@lists.php.net', $classification->confirmationAddress);
    }

    public function testMatchesTheWelcomeMessage(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'internals-help@lists.php.net',
            subject: 'WELCOME to internals@lists.php.net',
        ));

        self::assertSame(InboundEmailKind::Welcome, $classification->kind);
    }

    public function testMatchesTheGoodbyeMessage(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'internals-help@lists.php.net',
            subject: 'GOODBYE from internals@lists.php.net',
        ));

        self::assertSame(InboundEmailKind::Goodbye, $classification->kind);
    }

    public function testMatchesBounces(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'MAILER-DAEMON@mx.example.net',
            subject: 'Undelivered Mail Returned to Sender',
        ));

        self::assertSame(InboundEmailKind::Bounce, $classification->kind);
    }

    public function testClassifiesEverythingElseAsOther(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'random@example.org',
            subject: 'Hello there',
        ));

        self::assertSame(InboundEmailKind::Other, $classification->kind);
        self::assertNull($classification->confirmationAddress);
    }

    public function testDoesNotMatchCookieAddressesFromAForeignDomain(): void
    {
        $classification = $this->matcher->classify($this->message(
            from: 'internals+sc.123.abc@evil.example.org',
            subject: 'confirm subscribe to internals@lists.php.net',
        ));

        self::assertSame(InboundEmailKind::Other, $classification->kind);
    }

    private function message(string $from, string $subject, ?string $replyTo = null): InboundMessage
    {
        return new InboundMessage(
            from: $from,
            to: 'jane.doe+example.com.1f9c04@discourse.thephp.foundation',
            replyTo: $replyTo,
            subject: $subject,
            raw: 'raw message',
        );
    }
}
