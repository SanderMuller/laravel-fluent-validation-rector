<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class VariableKey
{
    public function read(ValidatedInput $safe, string $key): ?UploadedFile
    {
        return $safe->input($key) instanceof UploadedFile ? $safe->input($key) : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class VariableKey
{
    public function read(ValidatedInput $safe, string $key): ?UploadedFile
    {
        return $safe->file($key);
    }
}

?>
