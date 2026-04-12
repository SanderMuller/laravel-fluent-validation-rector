<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureSimplify;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'name' => FluentRule::string()->label('Full Name')->required()->max(255),
    'age' => FluentRule::integer()->label('Age')->required()->min(0),
    'site' => FluentRule::url()->label('Website')->nullable(),
];

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureSimplify;

use SanderMuller\FluentValidation\FluentRule;

$rules = [
    'name' => FluentRule::string('Full Name')->required()->max(255),
    'age' => FluentRule::integer('Age')->required()->min(0),
    'site' => FluentRule::url('Website')->nullable(),
];

?>
