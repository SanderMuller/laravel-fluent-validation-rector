<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipIntermediateStringableCall
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::tryFrom($safe->str('status')->lower()->toString());
    }
}

?>
