<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): end-to-end multi-rector
// scenario. RuleSet::from descent + method-chain preservation.
//
// Pins OQ #3 = (a) — inner-only descent. The
// `->check($data)->safe()` chain is sister-package API contract;
// collapsing or rewriting the chain would change runtime behavior
// (loses validation step or shifts return shape). Strict
// inner-Array_ replacement keeps semantics unchanged; consumer-
// visible diff is only the inner array converting from string-rule
// to FluentRule-chain shape.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.5.
//
// Contingency: this fixture's convert-shape is contingent on OQ #1 =
// (b) broad scope (visits any `RuleSet::from([Array_])` regardless
// of surrounding class type). The wrapping class is a plain service,
// not a FormRequest. If OQ #1 resolves to (a) narrow, this fixture
// transforms to a skip case.
class CheckSafeChainService
{
    public function validate(array $data): array
    {
        return RuleSet::from([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:0',
        ])->check($data)->safe();
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): end-to-end multi-rector
// scenario. RuleSet::from descent + method-chain preservation.
//
// Pins OQ #3 = (a) — inner-only descent. The
// `->check($data)->safe()` chain is sister-package API contract;
// collapsing or rewriting the chain would change runtime behavior
// (loses validation step or shifts return shape). Strict
// inner-Array_ replacement keeps semantics unchanged; consumer-
// visible diff is only the inner array converting from string-rule
// to FluentRule-chain shape.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.5.
//
// Contingency: this fixture's convert-shape is contingent on OQ #1 =
// (b) broad scope (visits any `RuleSet::from([Array_])` regardless
// of surrounding class type). The wrapping class is a plain service,
// not a FormRequest. If OQ #1 resolves to (a) narrow, this fixture
// transforms to a skip case.
class CheckSafeChainService
{
    public function validate(array $data): array
    {
        return RuleSet::from([
            'name' => FluentRule::string()->required()->max(255),
            'email' => FluentRule::email()->required(),
            'age' => FluentRule::integer()->nullable()->min(0),
        ])->check($data)->safe();
    }
}

?>
