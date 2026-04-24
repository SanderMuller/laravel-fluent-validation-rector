<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use App\Validation\DutchPostcodeRule;
use SanderMuller\FluentValidation\FluentRule;

// `->rule(new DutchPostcodeRule())` with `DutchPostcodeRule::class`
// allowlisted. Stays as the escape-hatch wrapper; silent skip.

$rules = [
    'zipcode' => FluentRule::string()->required()->rule(new DutchPostcodeRule()),
];

?>
