<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\StringOverridingRequest;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipOverriddenStringHelper
{
    public function handle(StringOverridingRequest $request): mixed
    {
        return PostStatus::tryFrom($request->str('status')->toString());
    }
}

?>
