<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteFieldBooleanToken
{
    public function schema(FluentSchema $rules): array
    {
        return [
            // The FluentSchema seed is promoted the same way as FluentRule::field().
            'is_public' => $rules->field()->sometimes()->rule('boolean'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteFieldBooleanToken
{
    public function schema(FluentSchema $rules): array
    {
        return [
            // The FluentSchema seed is promoted the same way as FluentRule::field().
            'is_public' => $rules->boolean()->sometimes(),
        ];
    }
}

?>
