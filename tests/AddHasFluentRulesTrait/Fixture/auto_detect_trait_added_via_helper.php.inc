<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// FluentRule lives in a custom-named helper, NOT in `rules()`. Pre-
// Phase-3+4 the trait rector only walked `rules()` for FluentRule
// usage, so this class would silently skip trait insertion — leaving
// the converted helper without runtime trait support. With the widened
// usesFluentRule walker the trait gets added correctly.
class AutoDetectTraitAddedViaHelper extends FormRequest
{
    public function rules(): array
    {
        return $this->editorRules();
    }

    private function editorRules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// FluentRule lives in a custom-named helper, NOT in `rules()`. Pre-
// Phase-3+4 the trait rector only walked `rules()` for FluentRule
// usage, so this class would silently skip trait insertion — leaving
// the converted helper without runtime trait support. With the widened
// usesFluentRule walker the trait gets added correctly.
class AutoDetectTraitAddedViaHelper extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return $this->editorRules();
    }

    private function editorRules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
