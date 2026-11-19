<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class UpperCaseClassName
{
    public function read(ValidatedInput $safe): ?object
    {
        return $safe->input('avatar') instanceof \Illuminate\Http\UPLOADEDFILE ? $safe->input('avatar') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class UpperCaseClassName
{
    public function read(ValidatedInput $safe): ?object
    {
        return $safe->file('avatar');
    }
}

?>
