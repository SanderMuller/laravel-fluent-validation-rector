<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class SkipDynamicMethodName
{
    public function handle(ValidatedInput $safe, string $tryFrom): mixed
    {
        return PostStatus::$tryFrom($safe->str('status')->toString());
    }
}

?>
