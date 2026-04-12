<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Http\Request;

function handleRequest(Request $request) {
    $validated = $request->validate([
        'title' => 'required|string|min:2|max:100',
        'body' => 'required|string',
        'published' => 'nullable|boolean',
    ]);
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use Illuminate\Http\Request;
use SanderMuller\FluentValidation\FluentRule;

function handleRequest(Request $request) {
    $validated = $request->validate([
        'title' => FluentRule::string()->required()->min(2)->max(100),
        'body' => FluentRule::string()->required(),
        'published' => FluentRule::boolean()->nullable(),
    ]);
}

?>
