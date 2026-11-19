<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class SkipUntypedReceiver
{
    public function read($safe): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : null;
    }
}

?>
