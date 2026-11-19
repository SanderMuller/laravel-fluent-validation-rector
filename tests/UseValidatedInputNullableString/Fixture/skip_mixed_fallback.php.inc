<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipMixedFallback
{
    public function read(ValidatedInput $safe, mixed $default): mixed
    {
        return $safe->filled('title') ? $safe->string('title')->value() : $default;
    }
}

?>
