<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SignHelpersOnNumeric
{
    public function rules(): array
    {
        // 1.19.0 sign helpers (positive / nonNegative / negative /
        // nonPositive). Implementation lives in the rector
        // (V1_REWRITE_TARGETS + RULE_NAME_TO_METHOD entries + literal-
        // zero gating in ParsesRulePayloads). Reflection-based
        // method-availability check skip-logs while the vendored
        // laravel-fluent-validation copy is on <1.19.0 (sign helpers
        // not yet present). Once the composer floor bumps to ^1.19,
        // flip this fixture to the transform shape (rules below
        // become ->positive() / ->nonNegative() / ->negative() /
        // ->nonPositive() zero-arg calls). Spec ref:
        // specs/fluent-validation-1-19-surface-support.md Phase 3.
        return [
            'pos' => FluentRule::numeric()->rule('gt:0'),
            'nn' => FluentRule::numeric()->rule('gte:0'),
            'neg' => FluentRule::numeric()->rule('lt:0'),
            'np' => FluentRule::numeric()->rule('lte:0'),
            'pos_f' => FluentRule::numeric()->rule('gt:0.0'),
            'arr' => FluentRule::numeric()->rule(['gte', 0]),
        ];
    }
}

?>
