<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipHasCondition
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->has('title') ? $safe->string('title')->value() : null;
    }
}

?>
