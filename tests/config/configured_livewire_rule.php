<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\AddHasFluentValidationTraitRector;

return RectorConfig::configure()
    ->withRules([
        AddHasFluentValidationTraitRector::class,
    ]);
