<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use Illuminate\Http\UploadedFile;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Source\StorePostRequest;

final class FormRequestSafe
{
    public function read(StorePostRequest $request): ?UploadedFile
    {
        $validated = $request->safe();

        return $validated->input('avatar') instanceof UploadedFile ? $validated->input('avatar') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Fixture;

use Illuminate\Http\UploadedFile;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputFile\Source\StorePostRequest;

final class FormRequestSafe
{
    public function read(StorePostRequest $request): ?UploadedFile
    {
        $validated = $request->safe();

        return $validated->file('avatar');
    }
}

?>
