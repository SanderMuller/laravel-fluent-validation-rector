<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class InWithIntermediateChain
{
    public function rules(): array
    {
        // Intermediate ->required()/->nullable() must not block resolution.
        return [
            'role' => FluentRule::string()->required()->nullable()->label('Role')->rule(['in', 'admin', 'user']),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class InWithIntermediateChain
{
    public function rules(): array
    {
        // Intermediate ->required()/->nullable() must not block resolution.
        return [
            'role' => FluentRule::string()->required()->nullable()->label('Role')->in(['admin', 'user']),
        ];
    }
}

?>
