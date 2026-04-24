<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Codex review must-have: closure body returns a method chain whose tail
// method does NOT return the receiver (`->getLabel()` returns `string|null`).
// Chain would pass a naïve "rooted in param" check but propagates a string
// at runtime. v1 rejects ALL method-chain bodies until reflection-based
// return-type probing lands — this fixture locks that conservative default.

$rules = [
    'field' => FluentRule::string()
        ->when(true, fn (StringRule $r): ?string => $r->getLabel())
        ->rule('max:255'),
];

?>
