<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class IsNotFilled
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->isNotFilled('rank') ? null : $safe->integer('rank');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableInteger\Source\StorePostRequest;

final class IsNotFilled
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->nullableInteger('rank');
    }
}

?>
