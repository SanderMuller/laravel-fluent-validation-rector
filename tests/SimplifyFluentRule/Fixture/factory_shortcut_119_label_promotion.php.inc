<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    // Source factory has a label arg → factory shortcut transfers
    // existing factory args, no label loss.
    'addr' => FluentRule::string('IP Address')->ipv4()->required(),
    // Source factory is arg-less + label() chained → label promotion
    // (existing pass) attaches label to the collapsed factory.
    'tz' => FluentRule::string()->timezone()->label('Time Zone'),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    // Source factory has a label arg → factory shortcut transfers
    // existing factory args, no label loss.
    'addr' => FluentRule::ipv4('IP Address')->required(),
    // Source factory is arg-less + label() chained → label promotion
    // (existing pass) attaches label to the collapsed factory.
    'tz' => FluentRule::timezone('Time Zone'),
];

?>
