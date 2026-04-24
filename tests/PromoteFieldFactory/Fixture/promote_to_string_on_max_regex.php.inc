<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class PromoteToStringOnMaxRegex
{
    public function rules(): array
    {
        return [
            // max + regex both intersect to {StringRule} — promote to string().
            'slug' => FluentRule::field()->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class PromoteToStringOnMaxRegex
{
    public function rules(): array
    {
        return [
            // max + regex both intersect to {StringRule} — promote to string().
            'slug' => FluentRule::string()->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
