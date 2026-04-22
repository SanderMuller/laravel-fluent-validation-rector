<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Conservative gate: arg-carrying shortcut (regex/enum) bails when
// the chain contains a label() call. Positional-slot threading
// (regex($pattern, $label) / enum($type, $cb, $label)) is out of v1
// scope, so we leave the chain alone rather than silently drop the
// label or rewrite it to a different positional slot. The existing
// label-promotion pass still attaches the label to the SOURCE
// factory (string('Code')), producing a partial collapse.
$rules = [
    'code' => FluentRule::string('Code')->regex('/^\d+$/')->required(),
    'pinned' => FluentRule::string()->regex('/^\d+$/')->label('PIN'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

// Conservative gate: arg-carrying shortcut (regex/enum) bails when
// the chain contains a label() call. Positional-slot threading
// (regex($pattern, $label) / enum($type, $cb, $label)) is out of v1
// scope, so we leave the chain alone rather than silently drop the
// label or rewrite it to a different positional slot. The existing
// label-promotion pass still attaches the label to the SOURCE
// factory (string('Code')), producing a partial collapse.
$rules = [
    'code' => FluentRule::string('Code')->regex('/^\d+$/')->required(),
    'pinned' => FluentRule::string('PIN')->regex('/^\d+$/'),
];

?>
