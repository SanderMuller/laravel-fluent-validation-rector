<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostFlag;

final class SkipZeroCaseEnum
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostFlag::tryFrom($safe->str('flag')->toString());
    }
}

?>
