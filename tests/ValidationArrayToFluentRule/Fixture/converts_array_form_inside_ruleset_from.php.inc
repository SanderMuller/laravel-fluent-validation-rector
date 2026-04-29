<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): array-form conversion
// inside `RuleSet::from([...])` wrapper. Mirrors mijntp's
// `AttachUserToCompanyAction` shape directly — array-form rule lists
// with mixed string rules and `Rule::*` factory calls.
//
// Pre-1.1.0 the wrapper case silently skips. Post-1.1.0 the inner
// Array_ converts; the RuleSet::from wrapper + return chain stay
// verbatim.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.2.
//
// Enum FQCN follows the existing fixture-file convention
// (cf. `array_with_rule_objects.php.inc`'s `\App\Enums\Status::class`):
// fictional cross-namespace FQCN, no local enum declaration. Parser
// resolves the FQCN as a class-string at AST time without requiring
// runtime existence; rector emits a `use` import in the expected
// output.
class WrappedAttachUserToCompanyRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            'role' => [
                'required',
                Rule::enum(\App\Enums\CompanyRoleEnum::class),
            ],
            'userId' => [
                'bail',
                'required',
                Rule::numeric()->integer(),
            ],
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Enums\CompanyRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): array-form conversion
// inside `RuleSet::from([...])` wrapper. Mirrors mijntp's
// `AttachUserToCompanyAction` shape directly — array-form rule lists
// with mixed string rules and `Rule::*` factory calls.
//
// Pre-1.1.0 the wrapper case silently skips. Post-1.1.0 the inner
// Array_ converts; the RuleSet::from wrapper + return chain stay
// verbatim.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.2.
//
// Enum FQCN follows the existing fixture-file convention
// (cf. `array_with_rule_objects.php.inc`'s `\App\Enums\Status::class`):
// fictional cross-namespace FQCN, no local enum declaration. Parser
// resolves the FQCN as a class-string at AST time without requiring
// runtime existence; rector emits a `use` import in the expected
// output.
class WrappedAttachUserToCompanyRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            'role' => FluentRule::field()->required()->enum(CompanyRoleEnum::class),
            'userId' => FluentRule::integer()->bail()->required(),
        ]);
    }
}

?>
