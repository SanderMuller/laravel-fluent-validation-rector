<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SkipSymfonyUploadedFile
{
    public function read(ValidatedInput $safe): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : null;
    }
}

?>
