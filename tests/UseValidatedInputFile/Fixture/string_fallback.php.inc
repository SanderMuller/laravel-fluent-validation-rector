<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class StringFallback
{
    public function read(ValidatedInput $safe): UploadedFile|string
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : 'none';
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class StringFallback
{
    public function read(ValidatedInput $safe): UploadedFile|string
    {
        return $safe->file('avatar') ?? 'none';
    }
}

?>
