<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class EmailMaxSupported
{
    public function rules(): array
    {
        // EmailRule has max() — asymmetric with min/between/exactly which
        // are absent. This case must rewrite.
        return [
            'email' => FluentRule::email()->rule('max:255'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class EmailMaxSupported
{
    public function rules(): array
    {
        // EmailRule has max() — asymmetric with min/between/exactly which
        // are absent. This case must rewrite.
        return [
            'email' => FluentRule::email()->max(255),
        ];
    }
}

?>
