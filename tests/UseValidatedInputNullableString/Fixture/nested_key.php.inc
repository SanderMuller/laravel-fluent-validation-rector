<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class NestedKey
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->filled('author.name') ? $safe->string('author.name')->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class NestedKey
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('author.name');
    }
}

?>
