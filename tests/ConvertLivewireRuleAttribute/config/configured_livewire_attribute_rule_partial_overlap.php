<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\ConvertLivewireRuleAttributeRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withConfiguredRule(ConvertLivewireRuleAttributeRector::class, [
        ConvertLivewireRuleAttributeRector::KEY_OVERLAP_BEHAVIOR => ConvertLivewireRuleAttributeRector::OVERLAP_BEHAVIOR_PARTIAL,
    ]);
