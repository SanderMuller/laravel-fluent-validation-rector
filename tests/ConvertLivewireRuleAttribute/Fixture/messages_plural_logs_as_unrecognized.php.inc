<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: `messages:` (plural) is not a Livewire-documented attribute arg.
 * Pre-Phase 3 the rector classified it as "dropped unsupported" — a
 * fabrication that pretended it was a real arg. The correction: log it as
 * an unrecognized arg (likely typo for `message:`). Rule conversion still
 * runs on the first-arg rule string; only the log-line category changed.
 */
final class MessagesPluralTypoComponent extends Component
{
    #[Validate('required|string', messages: ['required' => 'Name is required'])]
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
 * Phase 3: `messages:` (plural) is not a Livewire-documented attribute arg.
 * Pre-Phase 3 the rector classified it as "dropped unsupported" — a
 * fabrication that pretended it was a real arg. The correction: log it as
 * an unrecognized arg (likely typo for `message:`). Rule conversion still
 * runs on the first-arg rule string; only the log-line category changed.
 */
final class MessagesPluralTypoComponent extends Component
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
