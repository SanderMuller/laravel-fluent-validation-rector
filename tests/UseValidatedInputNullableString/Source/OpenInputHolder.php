<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source;

use SanderMuller\FluentValidation\ValidatedInput;

/**
 * Not final, and the property is public and mutable: on PHP 8.4 a subclass
 * can redeclare it with a get hook that runs on every read.
 */
class OpenInputHolder
{
    public ValidatedInput $safe;

    public function replace(ValidatedInput $safe): void
    {
        $this->safe = $safe;
    }
}
