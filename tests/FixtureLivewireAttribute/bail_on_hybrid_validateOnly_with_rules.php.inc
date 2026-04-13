<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class HybridValidateOnlyPage extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|email')]
    public string $email = '';

    public function saveName(): void
    {
        $this->validateOnly('name', [
            'name' => 'required|string|max:500',
        ]);
    }
}

?>
