<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\NormalizingRequest;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipOverriddenStr
{
    public function handle(NormalizingRequest $request): mixed
    {
        return PostStatus::tryFrom($request->str('status')->toString());
    }
}

?>
