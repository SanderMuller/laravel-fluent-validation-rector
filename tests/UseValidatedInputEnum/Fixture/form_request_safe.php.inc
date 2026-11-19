<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\StorePostRequest;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class FormRequestSafe
{
    public function handle(StorePostRequest $request): mixed
    {
        return PostStatus::tryFrom($request->safe()->str('status')->toString());
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\StorePostRequest;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class FormRequestSafe
{
    public function handle(StorePostRequest $request): mixed
    {
        return $request->safe()->enum('status', PostStatus::class);
    }
}

?>
