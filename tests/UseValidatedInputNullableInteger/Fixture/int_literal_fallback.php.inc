<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class IntLiteralFallback
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->filled('rank') ? $safe->integer('rank') : 10;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class IntLiteralFallback
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->nullableInteger('rank', 10);
    }
}

?>
