<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaEmailMaxRewrites
{
    public function schema(FluentSchema $rules): array
    {
        // Receiver inference resolves $rules->email() to EmailRule via the
        // FluentSchema seed, so the escape-hatch ->rule('max:255') lowers to
        // the native ->max(255) just as on a FluentRule:: chain.
        return [
            'email' => $rules->email()->rule('max:255'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaEmailMaxRewrites
{
    public function schema(FluentSchema $rules): array
    {
        // Receiver inference resolves $rules->email() to EmailRule via the
        // FluentSchema seed, so the escape-hatch ->rule('max:255') lowers to
        // the native ->max(255) just as on a FluentRule:: chain.
        return [
            'email' => $rules->email()
                ->max(255),
        ];
    }
}

?>
