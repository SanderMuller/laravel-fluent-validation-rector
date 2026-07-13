<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaFactoryCollapseEmail
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'email' => $rules->email()->message('Bad email.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaFactoryCollapseEmail
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'email' => $rules->email(message: 'Bad email.'),
        ];
    }
}

?>
