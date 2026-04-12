<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Http\Request;

function handleArrayRequest(Request $request) {
    $validated = $request->validate([
        'title' => ['required', 'string', 'min:2', 'max:100'],
        'body' => ['required', 'string'],
        'published' => ['nullable', 'boolean'],
        'tags' => ['nullable', 'array'],
    ]);
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Http\Request;
use SanderMuller\FluentValidation\FluentRule;

function handleArrayRequest(Request $request) {
    $validated = $request->validate([
        'title' => FluentRule::string()->required()->min(2)->max(100),
        'body' => FluentRule::string()->required(),
        'published' => FluentRule::boolean()->nullable(),
        'tags' => FluentRule::array()->nullable(),
    ]);
}

?>
