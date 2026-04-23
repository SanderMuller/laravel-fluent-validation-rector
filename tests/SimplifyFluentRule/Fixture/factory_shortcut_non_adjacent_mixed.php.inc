<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class Role
{
    public const string ADMIN = 'admin';
}

$rules = [
    // Zero-arg shortcut, non-adjacent: string() and url() separated.
    'homepage' => FluentRule::string()->nullable()->required()->url(),

    // Arg-carrying shortcut, non-adjacent: field() and enum() separated.
    'role' => FluentRule::field()->nullable()->bail()->enum(Role::class)->required(),

    // Arg-carrying shortcut, non-adjacent: string() and regex() separated.
    'slug' => FluentRule::string()->nullable()->required()->regex('/^[a-z-]+$/'),

    // Non-adjacent numeric → integer.
    'count' => FluentRule::numeric()->nullable()->required()->integer(),

    // Non-adjacent array → list.
    'tags' => FluentRule::array()->nullable()->required()->list(),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class Role
{
    public const string ADMIN = 'admin';
}

$rules = [
    // Zero-arg shortcut, non-adjacent: string() and url() separated.
    'homepage' => FluentRule::url()->nullable()->required(),

    // Arg-carrying shortcut, non-adjacent: field() and enum() separated.
    'role' => FluentRule::enum(Role::class)->nullable()->bail()->required(),

    // Arg-carrying shortcut, non-adjacent: string() and regex() separated.
    'slug' => FluentRule::regex('/^[a-z-]+$/')->nullable()->required(),

    // Non-adjacent numeric → integer.
    'count' => FluentRule::integer()->nullable()->required(),

    // Non-adjacent array → list.
    'tags' => FluentRule::list()->nullable()->required(),
];

?>
