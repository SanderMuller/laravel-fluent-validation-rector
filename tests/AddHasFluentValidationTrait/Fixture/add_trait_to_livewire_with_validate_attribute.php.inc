<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;

// Positive: Livewire component using the `#[Validate]` attribute on a
// property — Livewire's attribute-validation pipeline. Even though
// the property's attribute carries a string rule, the class also
// uses FluentRule in a helper (which other rectors will eventually
// rewrite the attribute around). The trait must be added because the
// attribute surface IS the validation surface that the trait hooks.
class CommentBox extends Component
{
    #[Validate('required|string|min:1')]
    public string $body = '';

    public function comment(): void
    {
        $this->validate([
            'body' => FluentRule::string()->required()->min(1),
        ]);
    }

    public function render()
    {
        return view('livewire.comment-box');
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentValidationTrait\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

// Positive: Livewire component using the `#[Validate]` attribute on a
// property — Livewire's attribute-validation pipeline. Even though
// the property's attribute carries a string rule, the class also
// uses FluentRule in a helper (which other rectors will eventually
// rewrite the attribute around). The trait must be added because the
// attribute surface IS the validation surface that the trait hooks.
class CommentBox extends Component
{
    use HasFluentValidation;

    #[Validate('required|string|min:1')]
    public string $body = '';

    public function comment(): void
    {
        $this->validate([
            'body' => FluentRule::string()->required()->min(1),
        ]);
    }

    public function render()
    {
        return view('livewire.comment-box');
    }
}

?>
