<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class VariableFallback
{
    public function handle(StorePostRequest $request, int $default): mixed
    {
        $safe = $request->safe();

        return $safe->filled('rank') ? $safe->integer('rank') : $default;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class VariableFallback
{
    public function handle(StorePostRequest $request, int $default): mixed
    {
        $safe = $request->safe();

        return $safe->nullableInteger('rank', $default);
    }
}

?>
