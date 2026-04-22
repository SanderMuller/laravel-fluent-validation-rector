<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class Status
{
    public const string ACTIVE = 'active';
}

$rules = [
    // field()->enum(X::class) → enum(X::class). enum is on FieldRule
    // via HasEmbeddedRules, but the static factory on FluentRule
    // returns FieldRule directly so the collapse is type-equivalent.
    'status' => FluentRule::field()->enum(Status::class)->required(),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class Status
{
    public const string ACTIVE = 'active';
}

$rules = [
    // field()->enum(X::class) → enum(X::class). enum is on FieldRule
    // via HasEmbeddedRules, but the static factory on FluentRule
    // returns FieldRule directly so the collapse is type-equivalent.
    'status' => FluentRule::enum(Status::class)->required(),
];

?>
