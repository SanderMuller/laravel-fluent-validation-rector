<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\OpenInputHolder;

final class SkipOverridablePropertyReceiver
{
    public function read(OpenInputHolder $holder): mixed
    {
        return $holder->safe->filled('title') ? $holder->safe->string('title')->value() : null;
    }
}

?>
