<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipIntFallback
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title') ? $safe->string('title')->value() : 0;
    }
}

?>
