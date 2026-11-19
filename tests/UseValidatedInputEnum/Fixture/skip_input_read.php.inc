<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipInputRead
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::tryFrom($safe->input('status'));
    }
}

?>
