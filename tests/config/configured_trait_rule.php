<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentRulesTraitRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        AddHasFluentRulesTraitRector::class,
    ]);
