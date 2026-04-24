<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Callback returns the parameter as a method chain (`$r->nullable()`). The
// chain LOOKS receiver-preserving but we can't statically prove every method
// returns `$this` without reflecting the declared return type — and methods
// like `getLabel()` / `compiledRules()` / `Macroable::__call` DO return
// something else. Conservative v1 bails; future hardening with return-type
// reflection will flip this to emit when every chain hop is a proven
// static-returning method.

$rules = [
    'url' => FluentRule::string()
        ->when(true, fn (StringRule $r): StringRule => $r->nullable())
        ->rule('max:255'),
];

?>
