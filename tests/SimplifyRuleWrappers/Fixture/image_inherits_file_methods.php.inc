<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class ImageInheritsFileMethods
{
    public function rules(): array
    {
        // ImageRule extends FileRule — min/max/between/exactly are inherited.
        // Reflection allowlist must pick them up via getMethod() walking the
        // inheritance chain so `image()->rule(...)` rewrites work too.
        return [
            'avatar' => FluentRule::image()->rule('min:1'),
            'cover' => FluentRule::image()->rule('max:5mb'),
            'thumb' => FluentRule::image()->rule('size:1024'),
            'banner' => FluentRule::image()->rule(['between', 100, 5000]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class ImageInheritsFileMethods
{
    public function rules(): array
    {
        // ImageRule extends FileRule — min/max/between/exactly are inherited.
        // Reflection allowlist must pick them up via getMethod() walking the
        // inheritance chain so `image()->rule(...)` rewrites work too.
        return [
            'avatar' => FluentRule::image()->min(1),
            'cover' => FluentRule::image()->max('5mb'),
            'thumb' => FluentRule::image()->exactly(1024),
            'banner' => FluentRule::image()->between(100, 5000),
        ];
    }
}

?>
