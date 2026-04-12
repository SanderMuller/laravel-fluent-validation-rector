<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\GroupWildcardRulesToEachRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withRules([
        GroupWildcardRulesToEachRector::class,
    ]);
