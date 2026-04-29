<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): string-rector standalone
// broad-scope coverage. RuleSet::from descent in a service-class
// method body — NOT inside a `rules(): RuleSet` FormRequest method.
// Mirrors the array-rector's fixture 4.3 shape but isolated to the
// string-rector test surface so OQ #1 = (b) is pinned independently
// for both rectors. Without this fixture, a partial implementation
// or future regression that widens only the array rector slips past
// the direct fixture set.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.3a.
//
// Contingency: convert-shape contingent on OQ #1 = (b) broad scope.
// If resolved to OQ #1 = (a) narrow, transforms to a skip case.
class ValidateInputService
{
    public function check(array $data): array
    {
        return RuleSet::from([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ])->check($data)->safe();
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): string-rector standalone
// broad-scope coverage. RuleSet::from descent in a service-class
// method body — NOT inside a `rules(): RuleSet` FormRequest method.
// Mirrors the array-rector's fixture 4.3 shape but isolated to the
// string-rector test surface so OQ #1 = (b) is pinned independently
// for both rectors. Without this fixture, a partial implementation
// or future regression that widens only the array rector slips past
// the direct fixture set.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.3a.
//
// Contingency: convert-shape contingent on OQ #1 = (b) broad scope.
// If resolved to OQ #1 = (a) narrow, transforms to a skip case.
class ValidateInputService
{
    public function check(array $data): array
    {
        return RuleSet::from([
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
        ])->check($data)->safe();
    }
}

?>
