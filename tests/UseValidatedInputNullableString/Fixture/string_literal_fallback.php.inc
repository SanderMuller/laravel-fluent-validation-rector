<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class StringLiteralFallback
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->filled('title') ? $safe->string('title')->value() : 'untitled';
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class StringLiteralFallback
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title', 'untitled');
    }
}

?>
