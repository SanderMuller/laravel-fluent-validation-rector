<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipLaravelValidatedInput
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->filled('title') ? $safe->string('title')->value() : null;
    }
}

?>
