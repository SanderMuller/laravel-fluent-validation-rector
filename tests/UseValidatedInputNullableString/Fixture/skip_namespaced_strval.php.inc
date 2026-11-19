<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture\ShadowedStrval;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipNamespacedStrval
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title') ? strval($safe->string('title')) : null;
    }
}

?>
