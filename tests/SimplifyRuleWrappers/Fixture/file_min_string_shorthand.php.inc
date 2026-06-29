<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FileMinStringShorthand
{
    public function rules(): array
    {
        // FileRule::min accepts int|string — '2mb' shorthand is valid.
        // The literal coercion preserves non-numeric tokens as strings.
        return [
            'avatar' => FluentRule::file()->rule('min:1'),
            'attachment' => FluentRule::file()->rule('max:50mb'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FileMinStringShorthand
{
    public function rules(): array
    {
        // FileRule::min accepts int|string — '2mb' shorthand is valid.
        // The literal coercion preserves non-numeric tokens as strings.
        return [
            'avatar' => FluentRule::file()
                ->min(1),
            'attachment' => FluentRule::file()
                ->max('50mb'),
        ];
    }
}

?>
