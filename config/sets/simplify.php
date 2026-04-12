<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\SimplifyFluentRuleRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(SimplifyFluentRuleRector::class);
};
