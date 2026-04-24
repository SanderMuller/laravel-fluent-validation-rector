<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class PromotePasswordMinWithClassConstLiteral
{
    public const int MIN_PASSWORD = 12;

    public function rules(): array
    {
        return [
            'password' => FluentRule::string()->required()->rule(Password::min(self::MIN_PASSWORD)),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\PromoteFieldFactory\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class PromotePasswordMinWithClassConstLiteral
{
    public const int MIN_PASSWORD = 12;

    public function rules(): array
    {
        return [
            'password' => FluentRule::password(self::MIN_PASSWORD)->required(),
        ];
    }
}

?>
