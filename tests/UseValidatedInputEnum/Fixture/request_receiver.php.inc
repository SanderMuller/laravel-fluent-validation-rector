<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Http\Request;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class RequestReceiver
{
    public function handle(Request $request): mixed
    {
        return PostStatus::tryFrom($request->str('status')->toString());
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Fixture;

use Illuminate\Http\Request;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source\PostStatus;

final class RequestReceiver
{
    public function handle(Request $request): mixed
    {
        return $request->enum('status', PostStatus::class);
    }
}

?>
