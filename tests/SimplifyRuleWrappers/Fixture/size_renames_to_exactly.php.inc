<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SizeRenamesToExactly
{
    public function rules(): array
    {
        // Regression pin — Laravel's `size:N` rule maps to fluent's
        // `exactly(N)` (intentional rename per TypedBuilderHint::for('size')
        // in the main package). Maintainers must NOT "fix" this back to
        // `->size(N)` — there is no `size()` method on any typed-rule class.
        return [
            'pin' => FluentRule::string()->rule('size:6'),
            'rgb' => FluentRule::array()->rule(['size', 3]),
            'cents' => FluentRule::numeric()->rule('size:100'),
            'thumb' => FluentRule::file()->rule('size:1024'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SizeRenamesToExactly
{
    public function rules(): array
    {
        // Regression pin — Laravel's `size:N` rule maps to fluent's
        // `exactly(N)` (intentional rename per TypedBuilderHint::for('size')
        // in the main package). Maintainers must NOT "fix" this back to
        // `->size(N)` — there is no `size()` method on any typed-rule class.
        return [
            'pin' => FluentRule::string()->exactly(6),
            'rgb' => FluentRule::array()->exactly(3),
            'cents' => FluentRule::numeric()->exactly(100),
            'thumb' => FluentRule::file()->exactly(1024),
        ];
    }
}

?>
