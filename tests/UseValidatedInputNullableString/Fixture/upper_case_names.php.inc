<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class UpperCaseNames
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->FILLED('title') ? $safe->STRING('title')->VALUE() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class UpperCaseNames
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title');
    }
}

?>
