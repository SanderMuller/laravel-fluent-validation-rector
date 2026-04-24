<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\PromoteFieldFactoryRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        PromoteFieldFactoryRector::class,
    ]);
