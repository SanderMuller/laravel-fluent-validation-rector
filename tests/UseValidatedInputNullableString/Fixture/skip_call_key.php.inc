<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipCallKey
{
    public function read(ValidatedInput $safe, string $key): mixed
    {
        return $safe->filled(strtolower($key)) ? $safe->string(strtolower($key))->value() : null;
    }
}

?>
