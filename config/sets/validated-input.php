<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputDateRector;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputEnumRector;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputFileRector;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputNullableIntegerRector;
use SanderMuller\FluentValidationRector\Rector\UseValidatedInputNullableStringRector;

// Opt-in. These rules do not convert validation rules: they rewrite reads of
// validated input to shorter accessors. The file(), nullableString() and
// nullableInteger() rules need the package's ValidatedInput and check that the installed version has
// the accessor; the date() and enum() rules use Laravel's own accessors.
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(UseValidatedInputDateRector::class);
    $rectorConfig->rule(UseValidatedInputEnumRector::class);
    $rectorConfig->rule(UseValidatedInputFileRector::class);
    $rectorConfig->rule(UseValidatedInputNullableIntegerRector::class);
    $rectorConfig->rule(UseValidatedInputNullableStringRector::class);
};
