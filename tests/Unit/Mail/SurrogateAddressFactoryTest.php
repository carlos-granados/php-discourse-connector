<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\SurrogateAddressFactory;
use PHPUnit\Framework\TestCase;

final class SurrogateAddressFactoryTest extends TestCase
{
    private SurrogateAddressFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SurrogateAddressFactory('surrogate.example.org');
    }

    public function testKeepsAReadableFormOfTheRealAddress(): void
    {
        $address = $this->factory->addressFor('jane.doe@example.com');

        self::assertMatchesRegularExpression(
            '/^jane\.doe\+example\.com\.[0-9a-f]{6}@surrogate\.example\.org$/',
            $address,
        );
    }

    public function testIsDeterministic(): void
    {
        self::assertSame(
            $this->factory->addressFor('jane.doe@example.com'),
            $this->factory->addressFor('jane.doe@example.com'),
        );
    }

    public function testNormalizesCaseAndWhitespace(): void
    {
        self::assertSame(
            $this->factory->addressFor('jane.doe@example.com'),
            $this->factory->addressFor('  Jane.Doe@Example.COM '),
        );
    }

    public function testDistinguishesAddressesWithTheSameReadableForm(): void
    {
        // Both collapse to "jane+doe+example.com" under plain @ -> + substitution
        $a = $this->factory->addressFor('jane+doe@example.com');
        $b = $this->factory->addressFor('jane@doe+example.com');

        self::assertNotSame($a, $b);
    }

    public function testSanitizesCharactersNotAllowedInEmailLocalParts(): void
    {
        $address = $this->factory->addressFor('jané dôe!!@example.com');

        [$localPart] = explode('@', $address);
        self::assertMatchesRegularExpression('/^[a-z0-9+._-]+$/', $localPart);
    }

    public function testTruncatesLongAddressesToTheLocalPartLimit(): void
    {
        $address = $this->factory->addressFor(str_repeat('a', 100).'@example.com');

        [$localPart] = explode('@', $address);
        self::assertLessThanOrEqual(64, \strlen($localPart));
    }

    public function testLocalPartNeverStartsOrEndsWithADotBeforeTheHash(): void
    {
        // 57 chars of readable prefix + truncation would leave a trailing dot without trimming
        $address = $this->factory->addressFor(str_repeat('a', 56).'.x@example.com');

        [$localPart] = explode('@', $address);
        self::assertStringNotContainsString('..', $localPart);
        self::assertStringStartsNotWith('.', $localPart);
    }
}
