<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\SimplifyRuleWrappersRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        SimplifyRuleWrappersRector::class,
    ]);
