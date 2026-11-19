<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class SkipPropertyFallback
{
    public ?string $defaultTitle;

    public function __construct(private readonly ValidatedInput $safe)
    {
    }

    public function read(): ?string
    {
        return $this->safe->filled('title') ? $this->safe->string('title')->value() : $this->defaultTitle;
    }
}

?>
