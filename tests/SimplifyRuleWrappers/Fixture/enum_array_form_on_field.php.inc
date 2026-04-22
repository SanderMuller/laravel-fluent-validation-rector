<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

enum Status: string
{
    case Active = 'active';
}

class EnumArrayFormOnField
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::field()->rule(['enum', Status::class]),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

enum Status: string
{
    case Active = 'active';
}

class EnumArrayFormOnField
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::field()->enum(Status::class),
        ];
    }
}

?>
