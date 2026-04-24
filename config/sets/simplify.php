<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\InlineMessageParamRector;
use SanderMuller\FluentValidationRector\Rector\PromoteFieldFactoryRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

return static function (RectorConfig $rectorConfig): void {
    // PromoteFieldFactoryRector runs first so its `FluentRule::field()` →
    // typed-factory promotions feed SimplifyRuleWrappersRector's next pass,
    // which then lowers `->rule('max:61')` into `->max(61)` natively on the
    // newly-typed receiver.
    $rectorConfig->rule(PromoteFieldFactoryRector::class);
    $rectorConfig->rule(SimplifyFluentRuleRector::class);
    $rectorConfig->rule(SimplifyRuleWrappersRector::class);
    $rectorConfig->rule(InlineMessageParamRector::class);
};
