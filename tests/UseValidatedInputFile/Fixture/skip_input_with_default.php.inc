<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class SkipInputWithDefault
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->input('avatar', null) instanceof UploadedFile ? $safe->input('avatar', null) : null;
    }
}

?>
