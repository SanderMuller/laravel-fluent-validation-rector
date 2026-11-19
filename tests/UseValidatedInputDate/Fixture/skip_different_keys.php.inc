<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipDifferentKeys
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->filled('published_at') ? $safe->date('updated_at') : null;
    }
}

?>
