<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class StrvalCall
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::tryFrom(strval($safe->string('status')));
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class StrvalCall
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->enum('status', PostStatus::class);
    }
}

?>
