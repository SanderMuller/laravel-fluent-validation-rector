<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

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

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

$validator = Validator::make($data, [
    'name' => FluentRule::string()->required()->max(255),
    'email' => FluentRule::email()->required()->unique('users'),
    'role' => FluentRule::string()->required()->in(['admin', 'editor', 'viewer']),
    'website' => FluentRule::url()->nullable(),
]);

?>
