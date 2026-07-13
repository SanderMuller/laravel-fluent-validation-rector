<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ConvertToFluentSchemaRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        ConvertToFluentSchemaRector::class,
    ]);
