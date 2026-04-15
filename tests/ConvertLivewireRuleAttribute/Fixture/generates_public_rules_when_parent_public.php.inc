<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport\BaseWithPublicRules;

final class ChildWithPublicParentRules extends BaseWithPublicRules
{
    #[Rule('required|string|max:255')]
    public string $name = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Rule;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport\BaseWithPublicRules;

final class ChildWithPublicParentRules extends BaseWithPublicRules
{
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
