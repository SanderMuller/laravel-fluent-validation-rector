<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteStringToPasswordWithDifferent
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'new_password' => $rules->string()->required()->different('current_password')->rule(Password::default()),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactoryPasswordModifiers\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteStringToPasswordWithDifferent
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'new_password' => $rules->password()->required()->different('current_password'),
        ];
    }
}

?>
