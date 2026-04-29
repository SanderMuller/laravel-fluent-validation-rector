<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): cross-fold-path
// intersection cell per PROCESS.md §1 — pre-conversion shapes
// inside RuleSet::from with wildcard keys. Multi-pass:
//
//   pass 1: ValidationStringToFluentRuleRector +
//           ValidationArrayToFluentRuleRector descend into
//           RuleSet::from([...]); convert string rules + array
//           forms in-place to FluentRule chains.
//   pass 2: GroupWildcardRulesToEachRector folds the wildcard
//           keys (`*.title`, `*.color`) into nested
//           `'*' => FluentRule::array()->children([...])` shape.
//
// The cell is non-trivial because the converter MUST run before
// wildcard fold; the wildcard fold's predicate accepts FluentRule-
// chain entries but not string-rule entries. If the converter
// silently skips inside RuleSet::from (pre-1.1.0 behavior), the
// wildcard fold ALSO silently skips because the predicate doesn't
// match string rules — double silent-skip. 1.1.0's RuleSet::from
// descent unblocks the wildcard fold transitively.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.6.
class WildcardInsideRuleSetRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        return RuleSet::from([
            '*.title' => 'required|string|max:255',
            '*.color' => [
                'nullable',
                Rule::in(['red', 'green', 'blue']),
            ],
        ]);
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelinePolish\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\RuleSet;
use SanderMuller\FluentValidation\HasFluentRules;

// 1.1.0 fixture (TDD pre-implementation): cross-fold-path
// intersection cell per PROCESS.md §1 — pre-conversion shapes
// inside RuleSet::from with wildcard keys. Multi-pass:
//
//   pass 1: ValidationStringToFluentRuleRector +
//           ValidationArrayToFluentRuleRector descend into
//           RuleSet::from([...]); convert string rules + array
//           forms in-place to FluentRule chains.
//   pass 2: GroupWildcardRulesToEachRector folds the wildcard
//           keys (`*.title`, `*.color`) into nested
//           `'*' => FluentRule::array()->children([...])` shape.
//
// The cell is non-trivial because the converter MUST run before
// wildcard fold; the wildcard fold's predicate accepts FluentRule-
// chain entries but not string-rule entries. If the converter
// silently skips inside RuleSet::from (pre-1.1.0 behavior), the
// wildcard fold ALSO silently skips because the predicate doesn't
// match string rules — double silent-skip. 1.1.0's RuleSet::from
// descent unblocks the wildcard fold transitively.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.6.
class WildcardInsideRuleSetRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return RuleSet::from(['*' => FluentRule::array()->children([
            'title' => FluentRule::string()->required()->max(255),
            'color' => FluentRule::field()->nullable()->in(['red', 'green', 'blue']),
        ])]);
    }
}

?>
