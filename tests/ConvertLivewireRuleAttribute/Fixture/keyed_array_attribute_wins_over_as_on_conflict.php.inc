<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3 codex-review regression pin: array-form precedence mirrors the
 * string-form rule. `attribute:` entries override `as:` entries for matching
 * keys; `as:` entries for keys absent from `attribute:` pass through. The
 * union `$attributeMap + $asMap` gives PHP-spread semantics where the
 * left operand wins on duplicate keys.
 */
final class KeyedArrayAsAttributeConflictComponent extends Component
{
    #[Validate([
        'todos' => 'required|array',
        'todos.*' => 'string|min:3',
    ], as: [
        'todos' => 'As todos',
        'todos.*' => 'As item',
    ], attribute: [
        'todos.*' => 'Attribute item',
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
 * Phase 3 codex-review regression pin: array-form precedence mirrors the
 * string-form rule. `attribute:` entries override `as:` entries for matching
 * keys; `as:` entries for keys absent from `attribute:` pass through. The
 * union `$attributeMap + $asMap` gives PHP-spread semantics where the
 * left operand wins on duplicate keys.
 */
final class KeyedArrayAsAttributeConflictComponent extends Component
{
    #[Validate]
    public array $todos = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'todos' => FluentRule::array()->required()->label('As todos'),
            'todos.*' => FluentRule::string()->min(3)->label('Attribute item'),
        ];
    }
}

?>
