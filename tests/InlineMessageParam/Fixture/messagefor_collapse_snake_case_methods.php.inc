<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseSnakeCaseMethods
{
    public function rules(): array
    {
        return [
            'slug' => FluentRule::string()->alphaDash()->messageFor('alpha_dash', 'Slug format.'),
            'code' => FluentRule::string()->alphaNumeric()->messageFor('alpha_num', 'Code format.'),
            'url' => FluentRule::string()->notRegex('/spam/')->messageFor('not_regex', 'No spam.'),
            'role' => FluentRule::string()->inArray('roles.*.name')->messageFor('in_array', 'Pick a role.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class MessageForCollapseSnakeCaseMethods
{
    public function rules(): array
    {
        return [
            'slug' => FluentRule::string()->alphaDash(message: 'Slug format.'),
            'code' => FluentRule::string()->alphaNumeric(message: 'Code format.'),
            'url' => FluentRule::string()->notRegex('/spam/', message: 'No spam.'),
            'role' => FluentRule::string()->inArray('roles.*.name', message: 'Pick a role.'),
        ];
    }
}

?>
