<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipUnknownClass
{
    public function handle(ValidatedInput $safe): mixed
    {
        return UnknownStatus::tryFrom($safe->str('status')->toString());
    }
}

?>
