<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ValidationStringToFluentRuleRector;

return RectorConfig::configure()
    ->withRules([
        ValidationStringToFluentRuleRector::class,
    ]);
