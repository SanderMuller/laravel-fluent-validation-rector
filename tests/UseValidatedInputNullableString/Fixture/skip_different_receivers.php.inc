<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipDifferentReceivers
{
    public function read(ValidatedInput $safe, ValidatedInput $other): mixed
    {
        return $safe->filled('title') ? $other->string('title')->value() : null;
    }
}

?>
