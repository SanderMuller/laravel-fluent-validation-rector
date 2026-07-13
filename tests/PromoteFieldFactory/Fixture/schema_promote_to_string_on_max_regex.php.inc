<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteToStringOnMaxRegex
{
    public function schema(FluentSchema $rules): array
    {
        return [
            // Same intersection as the FluentRule:: form: max + regex both
            // resolve to {StringRule}, so the FluentSchema seed is promoted
            // from field() to string() in place.
            'slug' => $rules->field()->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteToStringOnMaxRegex
{
    public function schema(FluentSchema $rules): array
    {
        return [
            // Same intersection as the FluentRule:: form: max + regex both
            // resolve to {StringRule}, so the FluentSchema seed is promoted
            // from field() to string() in place.
            'slug' => $rules->string()->rule('max:61')->rule('regex:/^[a-z]+$/'),
        ];
    }
}

?>
