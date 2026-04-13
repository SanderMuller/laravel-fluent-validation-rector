<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayNullableEmailAttributeComponent extends Component
{
    #[Validate(['nullable', 'email'])]
    public ?string $email = null;
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ArrayNullableEmailAttributeComponent extends Component
{
    public ?string $email = null;

    /**
     * @return array<string, FluentRule|string|array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->nullable(),
        ];
    }
}

?>
