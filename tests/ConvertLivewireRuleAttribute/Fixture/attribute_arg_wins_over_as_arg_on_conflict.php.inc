<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3 codex-review regression pin: `as:` and `attribute:` are documented
 * synonyms, so a user can technically supply both. When their values
 * conflict, `attribute:` wins — precedence is deterministic and independent
 * of source ordering. This fixture has `as:` listed first in source; the
 * rector still emits `->label('From attribute')` because `attribute:` wins
 * on conflict.
 */
final class AsAndAttributeConflictComponent extends Component
{
    #[Validate('required|string', as: 'From as', attribute: 'From attribute')]
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
 * Phase 3 codex-review regression pin: `as:` and `attribute:` are documented
 * synonyms, so a user can technically supply both. When their values
 * conflict, `attribute:` wins — precedence is deterministic and independent
 * of source ordering. This fixture has `as:` listed first in source; the
 * rector still emits `->label('From attribute')` because `attribute:` wins
 * on conflict.
 */
final class AsAndAttributeConflictComponent extends Component
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
