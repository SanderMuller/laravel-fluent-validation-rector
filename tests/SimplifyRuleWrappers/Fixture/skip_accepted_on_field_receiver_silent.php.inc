<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// `FluentRule::field()->rule('accepted')` — the explicit escape hatch.
// SimplifyRuleWrappersRector cannot promote `->rule('accepted')` to
// `->accepted()` because FieldRule has no `accepted()` method (the
// runtime semantics differ from BooleanRule's `accepted()`). The
// rector correctly leaves the chain unchanged. The suppress fix (GH
// #2, hihaho dogfood 2026-04-26) drops the SEMANTIC_DIVERGENCE_HINTS
// skip-log emit on this combination — the user already chose the
// escape hatch, so the hint reads as "you did the right thing" and
// belongs in `verbose`, not `actionable`.
$rules = [
    'tos' => FluentRule::field()->required()->rule('accepted'),
    'gdpr' => FluentRule::field()->required()->rule('declined'),
];

?>
