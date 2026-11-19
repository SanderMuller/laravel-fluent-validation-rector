<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class StringCast
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::tryFrom((string) $safe->str('status'));
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class StringCast
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->enum('status', PostStatus::class);
    }
}

?>
