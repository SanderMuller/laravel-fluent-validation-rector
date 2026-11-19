<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class MixedCaseNames
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->FILLED('rank') ? $safe->Integer('rank') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class MixedCaseNames
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->nullableInteger('rank');
    }
}

?>
