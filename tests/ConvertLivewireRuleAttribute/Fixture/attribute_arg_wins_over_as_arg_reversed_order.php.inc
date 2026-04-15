<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3 codex-review regression pin: the precedence of `attribute:` over
 * `as:` holds regardless of source ordering. This fixture has `attribute:`
 * listed first (reverse of the sibling conflict fixture) and still emits
 * `->label('From attribute')`. Before the fix, source order determined
 * the winner; now precedence is fixed.
 */
final class AttributeBeforeAsConflictComponent extends Component
{
    #[Validate('required|string', attribute: 'From attribute', as: 'From as')]
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
 * Phase 3 codex-review regression pin: the precedence of `attribute:` over
 * `as:` holds regardless of source ordering. This fixture has `attribute:`
 * listed first (reverse of the sibling conflict fixture) and still emits
 * `->label('From attribute')`. Before the fix, source order determined
 * the winner; now precedence is fixed.
 */
final class AttributeBeforeAsConflictComponent extends Component
{
    #[Validate]
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->label('From attribute'),
        ];
    }
}

?>
