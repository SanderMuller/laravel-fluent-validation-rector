<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipMaybeUndefinedVariableFallback
{
    public function read(ValidatedInput $safe, bool $useDefault): mixed
    {
        if ($useDefault) {
            $default = 'untitled';
        }

        return $safe->filled('title') ? $safe->string('title')->value() : $default;
    }
}

?>
