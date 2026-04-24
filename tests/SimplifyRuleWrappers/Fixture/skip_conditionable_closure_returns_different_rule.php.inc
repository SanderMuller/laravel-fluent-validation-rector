<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Codex review must-have: `when()` closure returns `FluentRule::array()`
// instead of the receiver. Stepping through the hop would miscompile the
// `max:255` rewrite (ArrayRule::max has different semantics). Bail.

$rules = [
    'field' => FluentRule::string()
        ->when(true, fn () => FluentRule::array())
        ->rule('max:255'),
];

?>
