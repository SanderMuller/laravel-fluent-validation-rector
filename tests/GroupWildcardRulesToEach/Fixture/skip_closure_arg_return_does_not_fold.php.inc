<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 codex-review HIGH #1 regression pin: rules() with a closure
// arg whose body returns RuleSet::from([...]) must NOT trigger fold
// or skip-log emit on the closure-internal return — that's a
// validator callback, not the rules() return value. Pre-fix the
// recursive Return_ traversal would have folded the closure's
// inner array (or logged about it), partially rewriting unrelated
// code. Post-fix the anchor is direct-child Return_ of
// $method->stmts only.
//
// rules() itself returns a bare array — the fold should run on
// THAT array (the wildcard fold happens to not apply because the
// keys aren't '*.X' shape, so the file ends up unchanged here).
class ClosureArgReturnDoesNotFoldRequest extends FormRequest
{
    public function rules(): array
    {
        $factory = function (): RuleSet {
            return RuleSet::from([
                '*.foo' => FluentRule::string()->required(),
            ]);
        };

        // The rules() body's actual return is a bare array with no
        // wildcard-prefix shape. The fold should leave this alone.
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
