<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Standard closure with side-effect body and no explicit return. Laravel's
// Conditionable `$callback($this, $value) ?? $this` substitutes `$this` when
// the callback returns null, so this shape provably preserves the receiver.

$rules = [
    'field' => FluentRule::string()
        ->when(true, function (StringRule $r): void {
            $r->nullable();
        })
        ->rule('max:255'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\Rules\StringRule;

// Standard closure with side-effect body and no explicit return. Laravel's
// Conditionable `$callback($this, $value) ?? $this` substitutes `$this` when
// the callback returns null, so this shape provably preserves the receiver.

$rules = [
    'field' => FluentRule::string()
        ->when(true, function (StringRule $r): void {
            $r->nullable();
        })->max(255),
];

?>
