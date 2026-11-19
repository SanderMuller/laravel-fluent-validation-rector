<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class SkipHasCondition
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->has('rank') ? $safe->integer('rank') : null;
    }
}

?>
