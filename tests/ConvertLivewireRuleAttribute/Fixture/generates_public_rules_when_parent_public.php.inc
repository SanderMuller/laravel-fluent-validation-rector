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
use Livewire\Attributes\Rule;
use SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureSupport\BaseWithPublicRules;

final class ChildWithPublicParentRules extends BaseWithPublicRules
{
    public string $name = '';

    /**
     * @return array<string, FluentRule|string|array<string, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
