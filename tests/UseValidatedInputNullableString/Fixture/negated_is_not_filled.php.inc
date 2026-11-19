<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class NegatedIsNotFilled
{
    public function read(ValidatedInput $safe): ?string
    {
        return ! $safe->isNotFilled('title') ? $safe->string('title')->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class NegatedIsNotFilled
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title');
    }
}

?>
