<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class EnumRuleFacadeOnString
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::string()->rule(Rule::enum(Status::class)),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class EnumRuleFacadeOnString
{
    public function rules(): array
    {
        return [
            'status' => FluentRule::string()->enum(Status::class),
        ];
    }
}

?>
