<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class IsNotFilledCheck
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->isNotFilled('published_at') ? null : $safe->date('published_at');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class IsNotFilledCheck
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->date('published_at');
    }
}

?>
