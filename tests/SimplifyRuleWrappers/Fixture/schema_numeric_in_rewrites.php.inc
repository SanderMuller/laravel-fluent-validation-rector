<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaNumericInRewrites
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'level' => $rules->numeric()->rule('in:1,2,3'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaNumericInRewrites
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'level' => $rules->numeric()
                ->in(['1', '2', '3']),
        ];
    }
}

?>
