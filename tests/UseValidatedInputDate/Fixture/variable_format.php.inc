<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class VariableFormat
{
    public function handle(ValidatedInput $safe, string $format): mixed
    {
        return $safe->filled('published_at') ? $safe->date('published_at', $format, null) : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Support\ValidatedInput;

final class VariableFormat
{
    public function handle(ValidatedInput $safe, string $format): mixed
    {
        return $safe->date('published_at', $format, null);
    }
}

?>
