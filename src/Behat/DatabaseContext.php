<?php

declare(strict_types=1);

namespace App\Behat;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterSuite;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;

/**
 * Wraps every scenario in a database transaction that is rolled back
 * afterwards (dama/doctrine-test-bundle), so scenarios never see each
 * other's data. The schema itself is created by the migrations run in
 * the composer "behat" script.
 */
final class DatabaseContext implements Context
{
    #[BeforeSuite]
    public static function keepStaticConnections(): void
    {
        StaticDriver::setKeepStaticConnections(true);
    }

    #[AfterSuite]
    public static function releaseStaticConnections(): void
    {
        StaticDriver::setKeepStaticConnections(false);
    }

    #[BeforeScenario]
    public function beginTransaction(): void
    {
        StaticDriver::beginTransaction();
    }

    #[AfterScenario]
    public function rollBack(): void
    {
        StaticDriver::rollBack();
    }
}
