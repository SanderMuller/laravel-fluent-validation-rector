<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\InputHolder;

final class SkipMagicPropertyReceiver
{
    public function read(InputHolder $holder): mixed
    {
        return $holder->safe->filled('title') ? $holder->safe->string('title')->value() : null;
    }
}

?>
