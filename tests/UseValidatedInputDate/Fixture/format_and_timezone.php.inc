<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class FormatAndTimezone
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->filled('published_at') ? $safe->date('published_at', 'Y-m-d', 'UTC') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class FormatAndTimezone
{
    public function handle(ValidatedInput $safe): mixed
    {
        return $safe->date('published_at', 'Y-m-d', 'UTC');
    }
}

?>
