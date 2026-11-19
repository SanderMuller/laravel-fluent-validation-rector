<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\StorePostRequest;

final class FormRequestSafe
{
    public function handle(StorePostRequest $request): array
    {
        $validated = $request->safe();

        return [
            'title' => $validated->filled('title') ? $validated->string('title')->value() : null,
            'slug' => $validated->filled('slug') ? $validated->string('slug')->value() : 'untitled',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\StorePostRequest;

final class FormRequestSafe
{
    public function handle(StorePostRequest $request): array
    {
        $validated = $request->safe();

        return [
            'title' => $validated->nullableString('title'),
            'slug' => $validated->nullableString('slug', 'untitled'),
        ];
    }
}

?>
