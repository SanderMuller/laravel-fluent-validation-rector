<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipSwappedBranches
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->filled('published_at') ? null : $safe->date('published_at');
    }
}

?>
