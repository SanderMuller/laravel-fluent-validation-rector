<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source\StorePostRequest;

final class FormRequestSafe
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->filled('published_at') ? $safe->date('published_at') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source\StorePostRequest;

final class FormRequestSafe
{
    public function handle(StorePostRequest $request): mixed
    {
        $safe = $request->safe();

        return $safe->date('published_at');
    }
}

?>
