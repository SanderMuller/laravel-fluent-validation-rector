<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class SkipCallFallback
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->filled('rank') ? $safe->integer('rank') : $this->defaultRank();
    }
}

?>
