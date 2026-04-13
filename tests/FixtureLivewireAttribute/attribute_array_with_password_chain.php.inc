<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayPasswordChainAttributeComponent extends Component
{
    #[Validate(['required', new Password(8)])]
    public string $password = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayPasswordChainAttributeComponent extends Component
{
    public string $password = '';

    /**
     * @return array<string, FluentRule|string|array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'password' => FluentRule::field()->required()->rule(new Password(8)),
        ];
    }
}

?>
