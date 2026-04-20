<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UpdateRulesReturnTypeDocblockRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(UpdateRulesReturnTypeDocblockRector::class);
};
