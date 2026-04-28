<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;
use SanderMuller\FluentValidation\StringRule;

final class MergeStaleDocblockComponent extends Component
{
    #[Rule('required|string|max:255')]
    public string $email = '';

    /**
     * @return array<string, StringRule>
     */
    public function rules(): array
    {
        return [
            'legacy' => \SanderMuller\FluentValidation\FluentRule::string(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Illuminate\Contracts\Validation\ValidationRule;
use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Rule;
use Livewire\Component;
use SanderMuller\FluentValidation\StringRule;

final class MergeStaleDocblockComponent extends Component
{
    public string $email = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'legacy' => FluentRule::string(), 'email' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
