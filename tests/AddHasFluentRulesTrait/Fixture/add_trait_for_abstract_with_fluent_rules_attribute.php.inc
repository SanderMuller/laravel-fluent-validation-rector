<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;

// #[FluentRules] on the abstract base's literal rules() method is the audit
// assertion that subclasses are safe (they don't array-manipulate
// parent::rules() or drop a base key). It lifts the abstract-with-rules()
// guard so the base receives HasFluentRules — letting the full pipeline reach
// an abstract base. Without the attribute the base is skipped (see the
// matching skip fixture).
abstract class AbstractAttributedFormRequest extends FormRequest
{
    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\HasFluentRules;

// #[FluentRules] on the abstract base's literal rules() method is the audit
// assertion that subclasses are safe (they don't array-manipulate
// parent::rules() or drop a base key). It lifts the abstract-with-rules()
// guard so the base receives HasFluentRules — letting the full pipeline reach
// an abstract base. Without the attribute the base is skipped (see the
// matching skip fixture).
abstract class AbstractAttributedFormRequest extends FormRequest
{
    use HasFluentRules;

    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
