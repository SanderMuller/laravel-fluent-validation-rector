<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\PostFields;

final class ClassConstantFallback
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->filled('title') ? $safe->string('title')->value() : PostFields::DEFAULT_TITLE;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\PostFields;

final class ClassConstantFallback
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title', PostFields::DEFAULT_TITLE);
    }
}

?>
