<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipIntegerRead
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('count') ? $safe->integer('count') : null;
    }
}

?>
