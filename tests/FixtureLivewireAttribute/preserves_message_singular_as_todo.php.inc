<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

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

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class ReportContentButton extends Component
{
    public string $category = '';

    public string $description = '';
    protected function rules(): array
    {
        return [
            'category' => FluentRule::field()->required(),
            'description' => FluentRule::field()->rule('max:2000'),
        ];
    }
}

?>
