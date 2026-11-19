<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputNullableStringRector;

return RectorConfig::configure()
    ->withRules([
        UseValidatedInputNullableStringRector::class,
    ]);
