<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipOtherRead
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->filled('published_at') ? $safe->string('published_at') : null;
    }
}

?>
