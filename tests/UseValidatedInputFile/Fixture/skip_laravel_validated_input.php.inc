<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use Illuminate\Support\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class SkipLaravelValidatedInput
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : null;
    }
}

?>
