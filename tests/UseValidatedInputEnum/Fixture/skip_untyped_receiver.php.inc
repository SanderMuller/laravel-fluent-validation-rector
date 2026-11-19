<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipUntypedReceiver
{
    public function handle($safe): mixed
    {
        return PostStatus::tryFrom($safe->str('status')->toString());
    }
}

?>
