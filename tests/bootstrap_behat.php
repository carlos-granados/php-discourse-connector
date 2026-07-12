<?php

declare(strict_types=1);

// Behat always runs against the test environment; pin it before the .env
// files are loaded (unlike PHPUnit, Behat has no equivalent of forcing
// server variables in its configuration).
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

require __DIR__.'/bootstrap.php';
