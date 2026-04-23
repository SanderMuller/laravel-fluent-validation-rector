<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class DisplayLocation
{
    public const string TOP = 'top';
}

$rules = [
    // field() and enum() separated by intermediate calls — hoist enum
    // as the factory, preserve the order of intermediate modifiers.
    'displayLocation' => FluentRule::field()->excludeUnless('type', 'chapter')->nullable()->enum(DisplayLocation::class),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class DisplayLocation
{
    public const string TOP = 'top';
}

$rules = [
    // field() and enum() separated by intermediate calls — hoist enum
    // as the factory, preserve the order of intermediate modifiers.
    'displayLocation' => FluentRule::enum(DisplayLocation::class)->excludeUnless('type', 'chapter')->nullable(),
];

?>
