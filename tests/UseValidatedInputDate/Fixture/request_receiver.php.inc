<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Http\Request;

final class RequestReceiver
{
    public function handle(Request $request): mixed
    {
        return $request->FILLED('published_at') ? $request->Date('published_at') : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;

use Illuminate\Http\Request;

final class RequestReceiver
{
    public function handle(Request $request): mixed
    {
        return $request->Date('published_at');
    }
}

?>
