<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class NegatedFilled
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return ! $safe->filled('rank') ? 0 : $safe->integer('rank');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class NegatedFilled
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->nullableInteger('rank', 0);
    }
}

?>
