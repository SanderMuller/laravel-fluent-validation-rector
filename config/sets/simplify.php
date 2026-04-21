<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\SimplifyFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(SimplifyFluentRuleRector::class);
    $rectorConfig->rule(SimplifyRuleWrappersRector::class);
};
