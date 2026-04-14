<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ValidationArrayToFluentRuleRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        ValidationArrayToFluentRuleRector::class,
    ]);
