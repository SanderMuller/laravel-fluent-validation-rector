<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\PostFields;

final class ClassConstantKey
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->filled(PostFields::TITLE) ? $safe->string(PostFields::TITLE)->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\PostFields;

final class ClassConstantKey
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString(PostFields::TITLE);
    }
}

?>
