<?php declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: './.cache/rector',
        cacheClass: FileCacheStorage::class,
        containerCacheDirectory: './.cache/rectorContainer',
    )
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        AddArrowFunctionReturnTypeRector::class,
        EncapsedStringsToSprintfRector::class,
        ExplicitBoolCompareRector::class,
        InlineArrayReturnAssignRector::class,
        NullToStrictStringFuncCallArgRector::class,
        PrivatizeFinalClassMethodRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets()
    ->withImportNames()
    ->withEditorUrl('phpstorm://open?file=%file%&line=%line%')
    ->withParallel(300, 15, 15)
    ->withMemoryLimit('3G')
    ->withPhpSets(php83: true);
