<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArraySimpleAttributeComponent extends Component
{
    #[Rule(['required', 'string', 'max:255'])]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

final class ArraySimpleAttributeComponent extends Component
{
    public string $name = '';

    /**
     * @return array<string, FluentRule|string|array<string, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
