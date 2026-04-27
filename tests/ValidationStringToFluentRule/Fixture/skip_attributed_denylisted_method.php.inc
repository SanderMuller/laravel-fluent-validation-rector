<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Layer-2 warning fixture. The class qualifies via FormRequest
// ancestry. `casts()` is in the denylist; even though it carries
// `#[FluentRules]`, it MUST NOT be rewritten — the attribute is
// silently dropped and a skip-log warning is emitted. The class's
// real `rules()` method DOES convert because it qualifies via the
// FormRequest signal independently.
class CastsAttributedFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }

    #[FluentRules]
    public function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Layer-2 warning fixture. The class qualifies via FormRequest
// ancestry. `casts()` is in the denylist; even though it carries
// `#[FluentRules]`, it MUST NOT be rewritten — the attribute is
// silently dropped and a skip-log warning is emitted. The class's
// real `rules()` method DOES convert because it qualifies via the
// FormRequest signal independently.
class CastsAttributedFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }

    #[FluentRules]
    public function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}

?>
