<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipeline\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class SaveInteractionRequestShape extends FormRequest
{
    private const INTERACTIONS = 'interactions';

    public function rules(): array
    {
        return [
            self::INTERACTIONS . '.*.continue_button_display_mode' => [
                ['exclude_unless', self::INTERACTIONS . '.*.type', \App\Enums\InteractionType::PAUSE->value],
                'sometimes',
                'string',
            ],
            self::INTERACTIONS . '.*.duration' => [
                'bail',
                ['required_unless', self::INTERACTIONS . '.*.type', ...\App\Enums\InteractionType::getValuesWithoutDuration()],
                'numeric',
            ],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipeline\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

class SaveInteractionRequestShape extends FormRequest
{
    use HasFluentRules;

    private const INTERACTIONS = 'interactions';

    public function rules(): array
    {
        return [self::INTERACTIONS => FluentRule::array()->each([
            'continue_button_display_mode' => FluentRule::string()->excludeUnless(self::INTERACTIONS . '.*.type', \App\Enums\InteractionType::PAUSE->value)->sometimes(),
            'duration' => FluentRule::numeric()->bail()->requiredUnless(self::INTERACTIONS . '.*.type', ...\App\Enums\InteractionType::getValuesWithoutDuration()),
        ])];
    }
}

?>
