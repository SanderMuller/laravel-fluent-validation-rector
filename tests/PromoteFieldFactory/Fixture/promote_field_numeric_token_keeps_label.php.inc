<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// numeric() takes the label as its first argument, the same as field().
class PromoteFieldNumericTokenKeepsLabel
{
    public function rules(): array
    {
        return [
            'price' => FluentRule::field('Price')->rule('numeric'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// numeric() takes the label as its first argument, the same as field().
class PromoteFieldNumericTokenKeepsLabel
{
    public function rules(): array
    {
        return [
            'price' => FluentRule::numeric('Price'),
        ];
    }
}

?>
