<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

final class CallFallback
{
    public function read(ValidatedInput $safe, Collection $placeholders): mixed
    {
        return $safe->input('avatar') instanceof UploadedFile ? $safe->input('avatar') : $placeholders->first();
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

final class CallFallback
{
    public function read(ValidatedInput $safe, Collection $placeholders): mixed
    {
        return $safe->file('avatar') ?? $placeholders->first();
    }
}

?>
