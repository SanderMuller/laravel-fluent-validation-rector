<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostRating;

final class SkipNumericCaseEnum
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostRating::tryFrom($safe->str('rating')->toString());
    }
}

?>
