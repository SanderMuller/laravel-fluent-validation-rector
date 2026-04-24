<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Named args for 3-arg `when()` — resolver correctly picks callbacks by
// `Arg::$name`. Both closure bodies are method-chains which v1 can't prove
// receiver-preserving. Skip (same as the positional-args variant).

$rules = [
    'field' => FluentRule::string()
        ->when(
            value: true,
            callback: fn (StringRule $r): StringRule => $r->nullable(),
            default: fn (StringRule $r): StringRule => $r->filled(),
        )
        ->rule('max:255'),
];

?>
