<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;

// Class has attrs but no explicit `$this->validate([...])` anywhere.
// Partial mode behaves identically to default behavior — all attrs convert.

final class NoExplicitValidatePage extends Component
{
    #[Validate('required|string')]
    public string $name = '';

    #[Validate('nullable|email')]
    public ?string $email = null;
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

// Class has attrs but no explicit `$this->validate([...])` anywhere.
// Partial mode behaves identically to default behavior — all attrs convert.

final class NoExplicitValidatePage extends Component
{
    #[Validate]
    public string $name = '';

    #[Validate]
    public ?string $email = null;

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
            'email' => FluentRule::email()->nullable(),
        ];
    }
}

?>
