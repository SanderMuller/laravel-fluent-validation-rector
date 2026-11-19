<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class SkipDifferentKeys
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('cover') : null;
    }
}

?>
