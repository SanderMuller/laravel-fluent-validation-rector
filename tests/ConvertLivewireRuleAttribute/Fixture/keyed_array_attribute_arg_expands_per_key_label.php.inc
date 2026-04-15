<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: `attribute:` behaves identically to `as:` in keyed-array form.
 * Documented Livewire v3 example uses `attribute: ['todos.*' => 'todo']`
 * to rename sub-keys in validation messages. Expected output matches the
 * `as:` variant — both produce per-entry `->label()` calls.
 */
final class KeyedArrayAttributeLabelComponent extends Component
{
    #[Validate([
        'todos' => 'required|array',
        'todos.*' => 'string|min:3',
    ], attribute: [
        'todos.*' => 'todo',
    ])]
    public array $todos = [];
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: `attribute:` behaves identically to `as:` in keyed-array form.
 * Documented Livewire v3 example uses `attribute: ['todos.*' => 'todo']`
 * to rename sub-keys in validation messages. Expected output matches the
 * `as:` variant — both produce per-entry `->label()` calls.
 */
final class KeyedArrayAttributeLabelComponent extends Component
{
    #[Validate]
    public array $todos = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'todos' => FluentRule::array()->required(),
            'todos.*' => FluentRule::string()->min(3)->label('todo'),
        ];
    }
}

?>
