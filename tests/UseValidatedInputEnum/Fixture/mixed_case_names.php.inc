<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class MixedCaseNames
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::TRYFROM($safe->STR('status')->ToString());
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class MixedCaseNames
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->enum('status', PostStatus::class);
    }
}

?>
