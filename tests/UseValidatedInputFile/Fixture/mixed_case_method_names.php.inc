<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class MixedCaseMethodNames
{
    public function read(ValidatedInput $safe): ?UploadedFile
    {
        return $safe->Input('avatar') instanceof UploadedFile ? $safe->INPUT('avatar') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class MixedCaseMethodNames
{
    public function read(ValidatedInput $safe): ?UploadedFile
    {
        return $safe->file('avatar');
    }
}

?>
