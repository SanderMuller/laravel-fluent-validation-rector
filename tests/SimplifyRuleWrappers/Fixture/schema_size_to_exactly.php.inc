<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

// Laravel's `size:N` maps to fluent's `exactly(N)` on the schema spelling
// exactly as on the FluentRule:: spelling.
class SchemaSizeToExactly
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'code' => $rules->string()->rule('size:6'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

// Laravel's `size:N` maps to fluent's `exactly(N)` on the schema spelling
// exactly as on the FluentRule:: spelling.
class SchemaSizeToExactly
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'code' => $rules->string()
                ->exactly(6),
        ];
    }
}

?>
