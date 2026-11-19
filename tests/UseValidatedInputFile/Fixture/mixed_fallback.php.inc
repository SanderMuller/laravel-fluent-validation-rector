<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class MixedFallback
{
    public function read(ValidatedInput $safe, mixed $fallback): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : $fallback;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;

final class MixedFallback
{
    public function read(ValidatedInput $safe, mixed $fallback): mixed
    {
        return $safe->file('avatar') ?? $fallback;
    }
}

?>
