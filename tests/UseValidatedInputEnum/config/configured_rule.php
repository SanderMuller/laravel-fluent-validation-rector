<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputEnumRector;

return RectorConfig::configure()
    ->withRules([
        UseValidatedInputEnumRector::class,
    ]);
