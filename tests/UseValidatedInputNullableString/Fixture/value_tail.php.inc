<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class ValueTail
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->filled('title') ? $safe->string('title')->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class ValueTail
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title');
    }
}

?>
