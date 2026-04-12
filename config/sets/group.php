<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\GroupWildcardRulesToEachRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(GroupWildcardRulesToEachRector::class);
};
