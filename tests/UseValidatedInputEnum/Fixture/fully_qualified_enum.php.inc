<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;

final class FullyQualifiedEnum
{
    public function handle(ValidatedInput $safe): mixed
    {
        return \SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus::tryFrom($safe->str('status')->toString());
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Support\ValidatedInput;

final class FullyQualifiedEnum
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->enum('status', \SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus::class);
    }
}

?>
