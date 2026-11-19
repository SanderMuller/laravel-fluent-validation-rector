<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class SkipDifferentRead
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar')->path() : null;
    }
}

?>
