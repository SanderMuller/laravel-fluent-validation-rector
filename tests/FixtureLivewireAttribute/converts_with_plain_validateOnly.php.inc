<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class PlainValidateOnlyPage extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    public function saveName(): void
    {
        $this->validateOnly('name');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class PlainValidateOnlyPage extends Component
{
    public string $name = '';

    public function saveName(): void
    {
        $this->validateOnly('name');
    }

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
