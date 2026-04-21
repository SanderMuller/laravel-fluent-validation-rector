<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use ReflectionClass;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

it('all set list constants resolve to readable files', function (): void {
    $constants = (new ReflectionClass(FluentValidationSetList::class))->getConstants();

    expect($constants)->not->toBeEmpty();

    foreach ($constants as $name => $path) {
        expect($path)
            ->toBeReadableFile("Set constant {$name} does not point to a readable file");
    }
});

it('ALL set does not include SIMPLIFY', function (): void {
    $allContent = file_get_contents(FluentValidationSetList::ALL);

    expect($allContent)
        ->toContain('convert.php')
        ->toContain('group.php')
        ->toContain('traits.php')
        ->not->toContain('simplify.php');
});

it('ALL set does not include POLISH', function (): void {
    $allContent = file_get_contents(FluentValidationSetList::ALL);

    expect($allContent)->not->toContain('polish.php');
});

it('POLISH set registers only the narrowing rule', function (): void {
    $polishContent = file_get_contents(FluentValidationSetList::POLISH);

    expect($polishContent)
        ->toContain('UpdateRulesReturnTypeDocblockRector');
});

it('SIMPLIFY set registers SimplifyRuleWrappers after SimplifyFluentRule', function (): void {
    $simplifyContent = file_get_contents(FluentValidationSetList::SIMPLIFY);

    expect($simplifyContent)
        ->toContain('SimplifyFluentRuleRector')
        ->toContain('SimplifyRuleWrappersRector');

    $simplifyPos = strpos($simplifyContent, 'SimplifyFluentRuleRector::class');
    $wrappersPos = strpos($simplifyContent, 'SimplifyRuleWrappersRector::class');

    expect($simplifyPos)->toBeInt()
        ->and($wrappersPos)->toBeInt()
        ->and($wrappersPos)->toBeGreaterThan($simplifyPos);
});
