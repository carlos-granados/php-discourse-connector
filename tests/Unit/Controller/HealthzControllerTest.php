<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HealthzController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\TestCase;

final class HealthzControllerTest extends TestCase
{
    public function testReportsOkWhenTheDatabaseIsReachable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeQuery')->with('SELECT 1');

        $response = new HealthzController($connection)();

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $response->getContent());
    }

    public function testReportsAnErrorWhenTheDatabaseIsUnreachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(
            new class('connection refused') extends \Exception implements DBALException {},
        );

        $response = new HealthzController($connection)();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"error","database":"unreachable"}', (string) $response->getContent());
    }
}
