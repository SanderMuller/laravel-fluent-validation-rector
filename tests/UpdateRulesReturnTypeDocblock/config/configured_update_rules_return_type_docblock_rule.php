<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;

return static function (RectorConfig $rectorConfig): void {
    // Allowlist sample entries used by the `skip_mixed_with_allowlisted_*`
    // fixtures. The patterns don't match any shape in the other fixtures, so
    // adding them here doesn't change existing emit/skip outcomes.
    $rectorConfig->ruleWithConfiguration(UpdateRulesReturnTypeDocblockRector::class, [
        UpdateRulesReturnTypeDocblockRector::TREAT_AS_FLUENT_COMPATIBLE => [
            ['App\\Models\\Question', 'existsRule'],
            'App\\Validation\\DutchPostcodeRule',
        ],
    ]);
};
