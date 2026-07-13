<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\MessageIdFactory;
use PHPUnit\Framework\TestCase;

final class MessageIdFactoryTest extends TestCase
{
    public function testBuildsTheCanonicalDiscoursePostMessageId(): void
    {
        $factory = new MessageIdFactory('https://discourse.example.test');

        self::assertSame('topic/3/7@discourse.example.test', $factory->forPost(3, 7));
    }

    public function testFallsBackToAPlaceholderHostForAnInvalidUrl(): void
    {
        $factory = new MessageIdFactory('not-a-url');

        self::assertSame('topic/1/2@discourse', $factory->forPost(1, 2));
    }
}
