<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

final class TypedPropertyComponent extends Component
{
    #[Validate('required', message: 'Please select a category.')]
    public string $category = '';

    #[Validate('max:2000')]
    public string $description = '';

    #[Validate('min:1')]
    public int $count = 0;

    #[Validate('required')]
    public bool $confirmed = false;

    #[Validate('max:50')]
    public string $title = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

final class TypedPropertyComponent extends Component
{
    public string $category = '';

    public string $description = '';

    public int $count = 0;

    public bool $confirmed = false;

    public string $title = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'category' => FluentRule::string()->required(),
            'description' => FluentRule::string()->max(2000),
            'count' => FluentRule::integer()->min(1),
            'confirmed' => FluentRule::boolean()->required(),
            'title' => FluentRule::string()->max(50),
        ];
    }
}

?>
