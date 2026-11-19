<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipLaravelValidatedInput
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->filled('rank') ? $safe->integer('rank') : null;
    }
}

?>
