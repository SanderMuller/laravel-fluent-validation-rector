<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRules;

// Positive control for Layer 1: `#[FluentRules]` on a non-denylisted
// method qualifies the class. The attributed method converts. The
// non-attributed sibling does NOT auto-detect because the class
// qualifies only via the attribute (per-method scoping).
class AttributedNonDenylistedQualifier
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'title' => 'required|string',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;

// Positive control for Layer 1: `#[FluentRules]` on a non-denylisted
// method qualifies the class. The attributed method converts. The
// non-attributed sibling does NOT auto-detect because the class
// qualifies only via the attribute (per-method scoping).
class AttributedNonDenylistedQualifier
{
    #[FluentRules]
    public function rulesWithoutPrefix(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
