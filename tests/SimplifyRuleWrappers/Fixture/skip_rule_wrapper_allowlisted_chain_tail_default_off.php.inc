<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use App\Models\Question;
use SanderMuller\FluentValidation\FluentRule;

// Chain-tail `->where(...)` on an allowlisted static factory. Default
// config leaves `ALLOW_CHAIN_TAIL_ON_ALLOWLISTED => false` so chain tails
// are NOT trusted — the wrapper is not treated as allowlisted and the
// parse-fail verbose skip fires (invisible in default mode).

$rules = [
    'question' => FluentRule::numeric()->required()->rule(Question::existsRule()->where('active', true)),
];

?>
