<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): RuleSet::from descent in
// an action-class method body — NOT inside a `rules(): RuleSet`
// FormRequest method. Pins the broad-scope OQ #1 = (b) resolution:
// the converter visits any `RuleSet::from([Array_])` static call
// regardless of surrounding-method-name qualification.
//
// Mijntp's exact `AttachUserToCompanyAction` shape: inline assignment
// + chain-method call + ->safe() retrieval. Pre-1.1.0 silently
// no-ops. Post-1.1.0 inner Array_ converts; outer chain stays
// verbatim.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.3.
//
// Contingency: this fixture's convert-shape is contingent on OQ #1 =
// (b) broad scope. If the spec resolves to OQ #1 = (a) narrow (only
// FormRequest `rules(): RuleSet` returns visited), this fixture
// transforms to a skip case (delete the `-----` separator; expected
// output equals input).
class AttachUserToCompanyAction
{
    public function execute(array $data): array
    {
        $safeInput = RuleSet::from([
            'role' => [
                'required',
                Rule::enum(\App\Enums\CompanyRoleEnum::class),
            ],
            'userId' => [
                'bail',
                'required',
                Rule::numeric()->integer(),
            ],
        ])->check($data)->safe();

        return $safeInput;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Enums\CompanyRoleEnum;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): RuleSet::from descent in
// an action-class method body — NOT inside a `rules(): RuleSet`
// FormRequest method. Pins the broad-scope OQ #1 = (b) resolution:
// the converter visits any `RuleSet::from([Array_])` static call
// regardless of surrounding-method-name qualification.
//
// Mijntp's exact `AttachUserToCompanyAction` shape: inline assignment
// + chain-method call + ->safe() retrieval. Pre-1.1.0 silently
// no-ops. Post-1.1.0 inner Array_ converts; outer chain stays
// verbatim.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.3.
//
// Contingency: this fixture's convert-shape is contingent on OQ #1 =
// (b) broad scope. If the spec resolves to OQ #1 = (a) narrow (only
// FormRequest `rules(): RuleSet` returns visited), this fixture
// transforms to a skip case (delete the `-----` separator; expected
// output equals input).
class AttachUserToCompanyAction
{
    public function execute(array $data): array
    {
        $safeInput = RuleSet::from([
            'role' => FluentRule::field()->required()->enum(CompanyRoleEnum::class),
            'userId' => FluentRule::integer()->bail()->required(),
        ])->check($data)->safe();

        return $safeInput;
    }
}

?>
