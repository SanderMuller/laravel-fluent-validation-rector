<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class NegatedInstanceof
{
    public function read(ValidatedInput $safe): ?UploadedFile
    {
        return ! $safe->input('avatar') instanceof UploadedFile ? null : $safe->input('avatar');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class NegatedInstanceof
{
    public function read(ValidatedInput $safe): ?UploadedFile
    {
        return $safe->file('avatar');
    }
}

?>
