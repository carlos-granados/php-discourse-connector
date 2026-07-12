<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    // Entity properties are declared as plain properties, not promoted (project preference)
    ->withSkip([ClassPropertyAssignToConstructorPromotionRector::class => [__DIR__.'/src/Entity']])
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withRootFiles()
    ->withPhpSets()
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true)
;
