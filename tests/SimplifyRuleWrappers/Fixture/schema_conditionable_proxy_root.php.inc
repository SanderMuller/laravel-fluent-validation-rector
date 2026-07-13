<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\Rules\StringRule;

// Receiver inference must walk THROUGH the identity `when()` proxy hop and
// still reach the FluentSchema seed `$rules->string()` at the root, so the
// trailing ->rule('max:255') lowers to ->max(255).
class SchemaConditionableProxyRoot
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'field' => $rules->string()
                ->when(true, fn (StringRule $r): StringRule => $r)
                ->rule('max:255'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\Rules\StringRule;

// Receiver inference must walk THROUGH the identity `when()` proxy hop and
// still reach the FluentSchema seed `$rules->string()` at the root, so the
// trailing ->rule('max:255') lowers to ->max(255).
class SchemaConditionableProxyRoot
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'field' => $rules->string()
                ->when(true, fn (StringRule $r): StringRule => $r)
                ->max(255),
        ];
    }
}

?>
