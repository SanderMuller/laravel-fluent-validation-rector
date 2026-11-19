<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostVisibility;

final class SkipBlankCaseEnum
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostVisibility::tryFrom($safe->str('visibility')->toString());
    }
}

?>
