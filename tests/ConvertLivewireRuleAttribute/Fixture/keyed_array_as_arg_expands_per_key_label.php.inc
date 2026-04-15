<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: when the first arg is a keyed-array, Livewire's `as:` named arg
 * is itself an array-keyed map from field path to display name. Each
 * expanded `rules()` entry gets the matching label as `->label('…')`.
 * Entries without a matching key pass through without a label.
 */
final class KeyedArrayAsLabelComponent extends Component
{
    #[Validate([
        'todos' => 'required|array',
        'todos.*' => 'string|min:3',
    ], as: [
        'todos' => 'To-dos',
        'todos.*' => 'Item',
    ])]
    public array $todos = [];
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
 * Phase 3: when the first arg is a keyed-array, Livewire's `as:` named arg
 * is itself an array-keyed map from field path to display name. Each
 * expanded `rules()` entry gets the matching label as `->label('…')`.
 * Entries without a matching key pass through without a label.
 */
final class KeyedArrayAsLabelComponent extends Component
{
    #[Validate]
    public array $todos = [];

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'todos' => FluentRule::array()->required()->label('To-dos'),
            'todos.*' => FluentRule::string()->min(3)->label('Item'),
        ];
    }
}

?>
