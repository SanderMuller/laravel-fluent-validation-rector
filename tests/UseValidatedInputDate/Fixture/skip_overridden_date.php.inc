<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source\NormalizingRequest;

final class SkipOverriddenDate
{
    public function handle(NormalizingRequest $request): mixed
    {
        return $request->filled('published_at') ? $request->date('published_at') : null;
    }
}

?>
