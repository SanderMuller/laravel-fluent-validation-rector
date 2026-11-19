<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class SkipMissingCheck
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->missing('published_at') ? null : $safe->date('published_at');
    }
}

?>
