<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
        ConvertLivewireRuleAttributeRector::MIGRATE_MESSAGES => true,
    ]);
