<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassConst\AddReadOnlyToConstantRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/apps',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor/*',
        __DIR__ . '/infra/volumes/*',
        __DIR__ . '/node_modules/*',
        __DIR__ . '/web/node_modules/*',
        __DIR__ . '/web/dist/*',
        __DIR__ . '/.cache/*',
    ])->withRootFiles()
    ->withPHPStanConfigs([
        __DIR__ . '/phpstan.neon',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
    ])
    ->withImportNames(false)
    ->withParallel(180); // seconds timeout
