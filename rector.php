<?php declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
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
        // The ConvertsValidationRules trait references SanderMuller\FluentValidation\FluentRules
        // as a string literal (not ::class) so PHPStan doesn't require the class to exist —
        // it ships in newer laravel-fluent-validation releases that our ^1.0 constraint doesn't
        // strictly require. Skip the rule here so the auto-fix workflow doesn't convert it back
        // and re-introduce the class.notFound error for consumers on older 1.x versions.
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/Rector/Concerns/ConvertsValidationRules.php',
        ],
        // ConvertLivewireRuleAttributeRector::processValidationRules() keeps its $array
        // parameter so the method signature matches the trait-required contract check
        // in PHPStan. The body doesn't use $array because this rector operates at the
        // attribute level, not on rules arrays — the parameter is present purely for
        // interface compatibility.
        RemoveUnusedPrivateMethodParameterRector::class => [
            __DIR__ . '/src/Rector/ConvertLivewireRuleAttributeRector.php',
        ],
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
