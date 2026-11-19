<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipFilledMultipleKeys
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title', 'slug') ? $safe->string('title')->value() : null;
    }
}

?>
