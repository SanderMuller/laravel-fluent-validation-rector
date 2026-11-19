<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\StorePostRequest;

final class SkipCallReceiver
{
    public function read(StorePostRequest $request): mixed
    {
        // The receiver is a call: the ternary builds two containers, the
        // rewrite would build one. Not rewritten.
        return $request->safe()->filled('title') ? $request->safe()->string('title')->value() : null;
    }
}

?>
