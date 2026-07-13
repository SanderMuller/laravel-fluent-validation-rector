<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaFactoryShortcuts
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'url' => $rules->string()->url()->required(),
            'count' => $rules->numeric()->integer()->required()->min(0),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaFactoryShortcuts
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'url' => $rules->url()
                ->required(),
            'count' => $rules->integer()
                ->required()
                ->min(0),
        ];
    }
}

?>
