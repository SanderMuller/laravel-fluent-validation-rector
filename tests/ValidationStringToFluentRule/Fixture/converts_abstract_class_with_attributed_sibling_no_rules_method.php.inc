<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Edge case: abstract class with `#[FluentRules]` on a non-`rules()`
// helper AND no literal `rules()` method. The abstract-with-rules
// guard should NOT fire (no `rules()` exists to be array-manipulated
// by subclasses), and the attributed helper should convert via the
// per-method opt-in. Pre-1.1 the guard incorrectly used the broader
// `hasRulesMethod()` check (which returns true on any
// `#[FluentRules]`-attributed sibling), emitting a misleading "Add
// #[FluentRules] to the rules() method" log even though no `rules()`
// method exists. Tightened to `hasLiteralRulesMethod()` in 1.1.
abstract class AbstractAttributedHelperOnly extends FluentValidator
{
    #[FluentRules]
    protected function rulesWithoutPrefix(): array
    {
        return [
            'title' => 'required|string|max:120',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\FluentValidator;

// Edge case: abstract class with `#[FluentRules]` on a non-`rules()`
// helper AND no literal `rules()` method. The abstract-with-rules
// guard should NOT fire (no `rules()` exists to be array-manipulated
// by subclasses), and the attributed helper should convert via the
// per-method opt-in. Pre-1.1 the guard incorrectly used the broader
// `hasRulesMethod()` check (which returns true on any
// `#[FluentRules]`-attributed sibling), emitting a misleading "Add
// #[FluentRules] to the rules() method" log even though no `rules()`
// method exists. Tightened to `hasLiteralRulesMethod()` in 1.1.
abstract class AbstractAttributedHelperOnly extends FluentValidator
{
    #[FluentRules]
    protected function rulesWithoutPrefix(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(120),
        ];
    }
}

?>
