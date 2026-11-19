<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipInputRead
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title') ? $safe->input('title') : null;
    }
}

?>
