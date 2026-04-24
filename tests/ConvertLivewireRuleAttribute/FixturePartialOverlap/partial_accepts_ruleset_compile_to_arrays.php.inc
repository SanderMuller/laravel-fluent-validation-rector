<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Livewire\Attributes\Validate;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// `RuleSet::compileToArrays([literalArray])` is the idiom collectiq uses
// to pass fluent rules through the compile step. Accepted wrapper —
// keys extracted from the inner literal, partial overlap logic applies
// as if the outer wrapping weren't there.

final class CompiledRulesPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    #[Validate('nullable|string')]
    public ?string $summary = null;

    public function save(): void
    {
        $this->validate(RuleSet::compileToArrays([
            'title' => FluentRule::string()->required(),
        ]));
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixturePartialOverlap;

use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// `RuleSet::compileToArrays([literalArray])` is the idiom collectiq uses
// to pass fluent rules through the compile step. Accepted wrapper —
// keys extracted from the inner literal, partial overlap logic applies
// as if the outer wrapping weren't there.

final class CompiledRulesPage extends Component
{
    #[Validate('required|string')]
    public string $title = '';

    #[Validate]
    public ?string $summary = null;

    public function save(): void
    {
        $this->validate(RuleSet::compileToArrays([
            'title' => FluentRule::string()->required(),
        ]));
    }

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'summary' => FluentRule::string()->nullable(),
        ];
    }
}

?>
