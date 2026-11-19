<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipShortTernary
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->string('title')->value() ?: null;
    }
}

?>
