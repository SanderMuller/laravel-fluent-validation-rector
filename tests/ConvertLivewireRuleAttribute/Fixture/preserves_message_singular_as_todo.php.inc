<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class ReportContentButton extends Component
{
    #[Validate('required', message: 'Please select a category.')]
    public string $category = '';

    #[Validate('max:2000')]
    public string $description = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ReportContentButton extends Component
{
    public string $category = '';

    public string $description = '';

    /**
     * @return array<string, FluentRule>
     */
    protected function rules(): array
    {
        return [
            'category' => FluentRule::string()->required(),
            'description' => FluentRule::string()->max(2000),
        ];
    }
}

?>
