<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: `message:` in array-form carries a map from rule name (or
 * `field.rule` path) to custom error text. Migration of those values into
 * a generated `messages(): array` method is Phase 4 scope; Phase 3 skip-logs
 * with a pointer. Rule conversion still proceeds on the first-arg keyed
 * map — only the messages payload is deferred.
 */
final class KeyedArrayMessageMapComponent extends Component
{
    #[Validate([
        'name' => 'required|string',
    ], message: [
        'name.required' => 'Name is required',
    ])]
    public string $name = '';
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
 * Phase 3: `message:` in array-form carries a map from rule name (or
 * `field.rule` path) to custom error text. Migration of those values into
 * a generated `messages(): array` method is Phase 4 scope; Phase 3 skip-logs
 * with a pointer. Rule conversion still proceeds on the first-arg keyed
 * map — only the messages payload is deferred.
 */
final class KeyedArrayMessageMapComponent extends Component
{
    #[Validate]
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
