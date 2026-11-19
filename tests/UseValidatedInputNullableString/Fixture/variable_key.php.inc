<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class VariableKey
{
    public function read(ValidatedInput $safe, string $key): ?string
    {
        return $safe->filled($key) ? $safe->string($key)->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class VariableKey
{
    public function read(ValidatedInput $safe, string $key): ?string
    {
        return $safe->nullableString($key);
    }
}

?>
