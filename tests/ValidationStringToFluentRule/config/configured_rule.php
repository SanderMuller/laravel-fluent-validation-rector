<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        ValidationStringToFluentRuleRector::class,
    ]);
