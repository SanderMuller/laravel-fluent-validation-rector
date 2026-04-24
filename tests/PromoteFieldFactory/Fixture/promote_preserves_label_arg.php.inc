<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class PromotePreservesLabelArg
{
    public function rules(): array
    {
        return [
            // FluentRule::field('Meta') → FluentRule::string('Meta') preserves the
            // label arg because StringRule::__construct binds `?string $label` as
            // its first positional param, same as FieldRule.
            'slug' => FluentRule::field('Meta')->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class PromotePreservesLabelArg
{
    public function rules(): array
    {
        return [
            // FluentRule::field('Meta') → FluentRule::string('Meta') preserves the
            // label arg because StringRule::__construct binds `?string $label` as
            // its first positional param, same as FieldRule.
            'slug' => FluentRule::string('Meta')->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
