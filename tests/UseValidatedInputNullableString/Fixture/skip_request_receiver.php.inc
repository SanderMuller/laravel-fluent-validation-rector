<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use Illuminate\Http\Request;

final class SkipRequestReceiver
{
    public function read(Request $request): mixed
    {
        return $request->filled('title') ? $request->string('title')->value() : null;
    }
}

?>
