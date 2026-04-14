<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Support\Facades\Validator;

$validator = Validator::make($data, [
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'role' => 'required|string|in:admin,editor,viewer',
    'website' => 'nullable|url',
]);

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Support\Facades\Validator;

$validator = Validator::make($data, [
    'name' => FluentRule::string()->required()->max(255),
    'email' => FluentRule::email()->required()->unique('users', 'email'),
    'role' => FluentRule::string()->required()->in(['admin', 'editor', 'viewer']),
    'website' => FluentRule::url()->nullable(),
]);

?>
