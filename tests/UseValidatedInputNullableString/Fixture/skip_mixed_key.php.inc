<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipMixedKey
{
    public function read(ValidatedInput $safe, mixed $key): mixed
    {
        return $safe->filled($key) ? $safe->string($key)->value() : null;
    }
}

?>
