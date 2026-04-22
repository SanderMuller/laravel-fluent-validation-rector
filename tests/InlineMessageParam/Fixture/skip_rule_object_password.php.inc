<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class SkipRuleObjectPassword
{
    public function rules(): array
    {
        // ->rule(new Password(...))->messageFor('password', ...) hits the
        // verbatim L11/L12 divergence skip-log template — `password.password`
        // key resolves on L12+ via the shortRule fallback, silently no-ops
        // on L11. Chain stays untouched to preserve version semantics.
        return [
            'password' => FluentRule::string()->rule(new Password(8))->messageFor('password', 'Set a stronger password.'),
        ];
    }
}

?>
