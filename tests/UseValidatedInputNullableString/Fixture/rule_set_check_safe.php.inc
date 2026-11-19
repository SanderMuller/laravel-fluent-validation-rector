<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

final class RuleSetCheckSafe
{
    public function handle(array $data): ?string
    {
        $safe = RuleSet::from([
            'title' => FluentRule::string()->nullable(),
        ])->check($data)->safe();

        return $safe->filled('title') ? $safe->string('title')->value() : null;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

final class RuleSetCheckSafe
{
    public function handle(array $data): ?string
    {
        $safe = RuleSet::from([
            'title' => FluentRule::string()->nullable(),
        ])->check($data)->safe();

        return $safe->nullableString('title');
    }
}

?>
