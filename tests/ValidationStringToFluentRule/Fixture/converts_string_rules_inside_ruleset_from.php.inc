<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): string-rule conversion
// inside `RuleSet::from([...])` wrapper. Pre-1.1.0 the converter
// anchors strictly on `Return_->expr = Array_`; the wrapper case
// silently skips. Post-1.1.0 the inner Array_ is the descent target;
// rules convert; the wrapper itself stays verbatim.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.1.
class WrappedStringRulesRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:0',
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): string-rule conversion
// inside `RuleSet::from([...])` wrapper. Pre-1.1.0 the converter
// anchors strictly on `Return_->expr = Array_`; the wrapper case
// silently skips. Post-1.1.0 the inner Array_ is the descent target;
// rules convert; the wrapper itself stays verbatim.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.1.
class WrappedStringRulesRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
            'age' => FluentRule::integer()->nullable()->min(0),
        ]);
    }
}

?>
