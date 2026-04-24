<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;
use SanderMuller\FluentValidationRector\Rector\InlineResolvableParentRulesRector;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;

return static function (RectorConfig $rectorConfig): void {
    // Inline `...parent::rules()` before the converters run so the resulting
    // flat-array shape reaches ValidationString/ArrayToFluentRuleRector.
    // Parents whose rules() is anything other than a plain array literal
    // stay spread (rector bails silently).
    $rectorConfig->rule(InlineResolvableParentRulesRector::class);
    $rectorConfig->rule(ValidationStringToFluentRuleRector::class);
    $rectorConfig->rule(ValidationArrayToFluentRuleRector::class);
    $rectorConfig->rule(ConvertLivewireRuleAttributeRector::class);
};
