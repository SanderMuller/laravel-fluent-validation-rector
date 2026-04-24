<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use App\Models\Question;
use SanderMuller\FluentValidation\FluentRule;

// `->rule(Question::existsRule())` with `[Question::class, 'existsRule']`
// allowlisted. Stays as the escape-hatch wrapper; silent skip (no log
// entry in verbose or default mode).

$rules = [
    'question' => FluentRule::numeric()->required()->rule(Question::existsRule()),
];

?>
