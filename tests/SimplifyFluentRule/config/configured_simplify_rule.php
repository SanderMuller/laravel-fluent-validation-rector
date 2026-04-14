<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\SimplifyFluentRuleRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        SimplifyFluentRuleRector::class,
    ]);
