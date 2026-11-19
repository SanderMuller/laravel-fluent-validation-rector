<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipHasCheck
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->has('published_at') ? $safe->date('published_at') : null;
    }
}

?>
