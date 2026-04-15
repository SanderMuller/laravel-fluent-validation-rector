<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase 3: Livewire v3 treats `attribute:` as a synonym for `as:` — both
 * set the field display name in validation error messages. String-form
 * `attribute: '…'` becomes `->label('…')`, same as the existing `as:`
 * handling. Identical output expected for both arg names.
 */
final class AttributeSynonymComponent extends Component
{
    #[Validate('required|string', attribute: 'user name')]
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
 * Phase 3: Livewire v3 treats `attribute:` as a synonym for `as:` — both
 * set the field display name in validation error messages. String-form
 * `attribute: '…'` becomes `->label('…')`, same as the existing `as:`
 * handling. Identical output expected for both arg names.
 */
final class AttributeSynonymComponent extends Component
{
    #[Validate]
    public string $name = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->label('user name'),
        ];
    }
}

?>
