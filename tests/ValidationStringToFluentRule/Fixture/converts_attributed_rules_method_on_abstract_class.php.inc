<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Abstract class with `#[FluentRules]` on `rules()` — the user has
// explicitly asserted that subclasses do NOT manipulate
// `parent::rules()` as a plain array. The method-scoped audit
// assertion lifts the abstract-with-rules guard.
abstract class AuditedAbstractRequest extends FormRequest
{
    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
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

// Abstract class with `#[FluentRules]` on `rules()` — the user has
// explicitly asserted that subclasses do NOT manipulate
// `parent::rules()` as a plain array. The method-scoped audit
// assertion lifts the abstract-with-rules guard.
abstract class AuditedAbstractRequest extends FormRequest
{
    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(255),
        ];
    }
}

?>
