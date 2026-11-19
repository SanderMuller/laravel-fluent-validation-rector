<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipSwappedBranches
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title') ? null : $safe->string('title')->value();
    }
}

?>
