<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class SkipSwappedBranches
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? null : $safe->input('avatar');
    }
}

?>
