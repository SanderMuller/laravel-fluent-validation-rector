<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ValidationStringToFluentRuleRector::class);
    $rectorConfig->rule(ValidationArrayToFluentRuleRector::class);
};
