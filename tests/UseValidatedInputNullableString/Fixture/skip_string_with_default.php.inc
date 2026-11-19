<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipStringWithDefault
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title') ? $safe->string('title', 'x')->value() : null;
    }
}

?>
