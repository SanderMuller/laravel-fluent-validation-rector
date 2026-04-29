<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet as ValidationRules;

// 1.1.0 fixture (TDD — guards Codex finding #2, medium severity):
// the package's `RuleSet` imported under an alias should still
// trigger the broad-scope RuleSet::from descent. Pre-fix the
// trait's bare-name match on `'RuleSet'` missed aliased imports
// (the AST resolves `ValidationRules::from(...)` to short-name
// `ValidationRules`, not `RuleSet`); post-fix the FQCN-resolution
// path matches regardless of import alias.
class AliasedRuleSetRequest extends FormRequest
{
    public function rules(): ValidationRules
    {
        return ValidationRules::from([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet as ValidationRules;

// 1.1.0 fixture (TDD — guards Codex finding #2, medium severity):
// the package's `RuleSet` imported under an alias should still
// trigger the broad-scope RuleSet::from descent. Pre-fix the
// trait's bare-name match on `'RuleSet'` missed aliased imports
// (the AST resolves `ValidationRules::from(...)` to short-name
// `ValidationRules`, not `RuleSet`); post-fix the FQCN-resolution
// path matches regardless of import alias.
class AliasedRuleSetRequest extends FormRequest
{
    public function rules(): ValidationRules
    {
        return ValidationRules::from([
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
        ]);
    }
}

?>
