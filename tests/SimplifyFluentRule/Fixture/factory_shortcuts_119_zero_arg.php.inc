<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'ipv4' => FluentRule::string()->ipv4()->required(),
    'ipv6' => FluentRule::string()->ipv6()->nullable(),
    'mac' => FluentRule::string()->macAddress(),
    'json' => FluentRule::string()->json()->required(),
    'tz' => FluentRule::string()->timezone(),
    'color' => FluentRule::string()->hexColor(),
    'site' => FluentRule::string()->activeUrl()->required(),
    'tags' => FluentRule::array()->list()->required(),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'ipv4' => FluentRule::ipv4()->required(),
    'ipv6' => FluentRule::ipv6()->nullable(),
    'mac' => FluentRule::macAddress(),
    'json' => FluentRule::json()->required(),
    'tz' => FluentRule::timezone(),
    'color' => FluentRule::hexColor(),
    'site' => FluentRule::activeUrl()->required(),
    'tags' => FluentRule::list()->required(),
];

?>
