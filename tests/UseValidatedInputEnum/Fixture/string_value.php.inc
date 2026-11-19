<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class StringValue
{
    public function handle(ValidatedInput $safe): mixed
    {
        return PostStatus::tryFrom($safe->string('status')->value());
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class StringValue
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->enum('status', PostStatus::class);
    }
}

?>
