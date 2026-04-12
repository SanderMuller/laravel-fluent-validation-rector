<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

$validator = Validator::make($data, [
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', Rule::unique('users')],
    'role' => ['required', 'string', Rule::in(['admin', 'editor', 'viewer'])],
    'website' => ['nullable', 'url'],
]);

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

$validator = Validator::make($data, [
    'name' => FluentRule::string()->required()->max(255),
    'email' => FluentRule::email()->required()->unique('users'),
    'role' => FluentRule::string()->required()->in(['admin', 'editor', 'viewer']),
    'website' => FluentRule::url()->nullable(),
]);

?>
