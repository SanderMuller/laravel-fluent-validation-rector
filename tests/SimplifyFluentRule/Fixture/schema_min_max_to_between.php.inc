<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaMinMaxToBetween
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required()->min(2)->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaMinMaxToBetween
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()
                ->required()
                ->between(2, 255),
        ];
    }
}

?>
