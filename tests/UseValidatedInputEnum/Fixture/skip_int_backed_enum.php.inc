<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostPriority;

final class SkipIntBackedEnum
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostPriority::tryFrom($safe->str('priority')->toString());
    }
}

?>
