<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaMessageForCollapseStringMin
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->min(3)->messageFor('min', 'Too short.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaMessageForCollapseStringMin
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()
                ->min(3, message: 'Too short.'),
        ];
    }
}

?>
