<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputFileRector;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputNullableStringRector;

// Opt-in. These rules do not convert validation rules: they rewrite reads of
// validated input to the accessors on the package's ValidatedInput. Each rule
// checks that the installed laravel-fluent-validation version has the accessor.
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(UseValidatedInputFileRector::class);
    $rectorConfig->rule(UseValidatedInputNullableStringRector::class);
};
