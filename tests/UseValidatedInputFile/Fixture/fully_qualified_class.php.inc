<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class FullyQualifiedClass
{
    public function read(ValidatedInput $safe): ?\Illuminate\Http\UploadedFile
    {
        return $safe->input('avatar') instanceof \Illuminate\Http\UploadedFile ? $safe->input('avatar') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;

final class FullyQualifiedClass
{
    public function read(ValidatedInput $safe): ?\Illuminate\Http\UploadedFile
    {
        return $safe->file('avatar');
    }
}

?>
