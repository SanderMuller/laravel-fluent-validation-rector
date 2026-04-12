<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentValidationTraitRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AddHasFluentRulesTraitRector::class);
    $rectorConfig->rule(AddHasFluentValidationTraitRector::class);
};
