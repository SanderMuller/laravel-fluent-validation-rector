<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Support\Collection;

final class SkipCallFallback
{
    public function read(ValidatedInput $safe, Collection $titles): mixed
    {
        return $safe->filled('title') ? $safe->string('title')->value() : $titles->first();
    }
}

?>
