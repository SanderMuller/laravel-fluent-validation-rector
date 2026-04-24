<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\InlineResolvableParentRulesRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        InlineResolvableParentRulesRector::class,
    ]);
