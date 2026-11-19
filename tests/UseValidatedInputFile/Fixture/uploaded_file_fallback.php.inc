<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class UploadedFileFallback
{
    public function read(ValidatedInput $safe, UploadedFile $placeholder): UploadedFile
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : $placeholder;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class UploadedFileFallback
{
    public function read(ValidatedInput $safe, UploadedFile $placeholder): UploadedFile
    {
        return $safe->file('avatar') ?? $placeholder;
    }
}

?>
