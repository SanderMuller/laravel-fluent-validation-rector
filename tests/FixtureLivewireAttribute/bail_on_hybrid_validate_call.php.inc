<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Validate;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

final class HybridAuthorOnboardingPage extends Component
{
    use HasFluentValidation;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:500')]
    public string $bio = '';

    public function saveProfile(): void
    {
        $this->validate([
            'name' => FluentRule::string()->required()->max(255),
            'bio' => FluentRule::string()->nullable()->max(500),
        ]);
    }
}

?>
