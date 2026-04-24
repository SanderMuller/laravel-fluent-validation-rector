<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

// Allowlist entries shared with the UpdateRulesReturnTypeDocblockRector
// fixture config so both rectors treat the same consumer rule factories
// as silent escape-hatch calls. Patterns don't match any other fixture's
// shapes — safe to enable in the default config.
return RectorConfig::configure()
    ->withImportNames()
    ->withConfiguredRule(SimplifyRuleWrappersRector::class, [
        SimplifyRuleWrappersRector::TREAT_AS_FLUENT_COMPATIBLE => [
            ['App\\Models\\Question', 'existsRule'],
            'App\\Validation\\DutchPostcodeRule',
        ],
    ]);
