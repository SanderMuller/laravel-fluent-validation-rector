<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source\BlankCheckOverridingRequest;

final class SkipOverriddenBlankCheck
{
    public function handle(BlankCheckOverridingRequest $request): mixed
    {
        return $request->filled('published_at') ? $request->date('published_at') : null;
    }
}

?>
