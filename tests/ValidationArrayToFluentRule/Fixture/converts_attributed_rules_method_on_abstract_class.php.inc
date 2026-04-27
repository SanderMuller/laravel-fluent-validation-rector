<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Abstract class with `#[FluentRules]` on `rules()` — array form. The
// method-scoped audit assertion lifts the abstract-with-rules guard;
// the array converter walks the body and emits FluentRule chains.
abstract class AuditedArrayAbstractRequest extends FormRequest
{
    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// Abstract class with `#[FluentRules]` on `rules()` — array form. The
// method-scoped audit assertion lifts the abstract-with-rules guard;
// the array converter walks the body and emits FluentRule chains.
abstract class AuditedArrayAbstractRequest extends FormRequest
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
