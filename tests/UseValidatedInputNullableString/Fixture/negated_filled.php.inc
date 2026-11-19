<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class NegatedFilled
{
    public function read(ValidatedInput $safe): ?string
    {
        return ! $safe->filled('title') ? 'untitled' : $safe->string('title')->value();
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class NegatedFilled
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title', 'untitled');
    }
}

?>
