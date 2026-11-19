<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class VariableFallback
{
    public function read(ValidatedInput $safe, ?string $default): ?string
    {
        return $safe->filled('title') ? $safe->string('title')->value() : $default;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class VariableFallback
{
    public function read(ValidatedInput $safe, ?string $default): ?string
    {
        return $safe->nullableString('title', $default);
    }
}

?>
