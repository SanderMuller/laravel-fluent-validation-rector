<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipNonNullFallback
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->filled('published_at') ? $safe->date('published_at') : now();
    }
}

?>
