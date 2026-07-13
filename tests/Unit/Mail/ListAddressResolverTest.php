<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\ListAddressResolver;
use PHPUnit\Framework\TestCase;

final class ListAddressResolverTest extends TestCase
{
    private ListAddressResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ListAddressResolver('internals@lists.php.net');
    }

    public function testDerivesTheNomailSubscribeAddress(): void
    {
        self::assertSame('internals+subscribe-nomail@lists.php.net', $this->resolver->subscribeAddress());
    }

    public function testDerivesTheNomailUnsubscribeAddress(): void
    {
        self::assertSame('internals+unsubscribe-nomail@lists.php.net', $this->resolver->unsubscribeAddress());
    }

    public function testExposesTheListAddressParts(): void
    {
        self::assertSame('internals@lists.php.net', $this->resolver->listAddress());
        self::assertSame('internals', $this->resolver->listLocalPart());
        self::assertSame('lists.php.net', $this->resolver->listDomain());
    }
}
