<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class StrAliasValue
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->filled('title') ? $safe->str('title')->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class StrAliasValue
{
    public function read(ValidatedInput $safe): ?string
    {
        return $safe->nullableString('title');
    }
}

?>
