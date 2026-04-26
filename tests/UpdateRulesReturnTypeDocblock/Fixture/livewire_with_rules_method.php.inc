<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Livewire\Attributes\Validate;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class LivewireWithRulesMethod
{
    use HasFluentValidation;

    #[Validate]
    public string $title = '';

    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use Livewire\Attributes\Validate;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentValidation;

class LivewireWithRulesMethod
{
    use HasFluentValidation;

    #[Validate]
    public string $title = '';

    /**
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
