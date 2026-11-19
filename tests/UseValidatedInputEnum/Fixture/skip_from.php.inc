<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipFrom
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::from($safe->str('status')->toString());
    }
}

?>
