<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteStringToPassword
{
    public function schema(FluentSchema $rules): array
    {
        // Trigger B on the FluentSchema seed: string()->rule(Password::default())
        // promotes the seed to password() in place and splices the redundant
        // ->rule() hop out, exactly as on a FluentRule:: chain.
        return [
            'password' => $rules->string()->required()->confirmed()->rule(Password::default()),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use SanderMuller\FluentValidation\FluentSchema;

class SchemaPromoteStringToPassword
{
    public function schema(FluentSchema $rules): array
    {
        // Trigger B on the FluentSchema seed: string()->rule(Password::default())
        // promotes the seed to password() in place and splices the redundant
        // ->rule() hop out, exactly as on a FluentRule:: chain.
        return [
            'password' => $rules->password()->required()->confirmed(),
        ];
    }
}

?>
