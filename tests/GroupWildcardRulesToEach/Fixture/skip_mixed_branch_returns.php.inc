<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 codex-review HIGH #2 regression pin: rules() with a bare-
// array return on one branch and a RuleSet::from([...]) return on
// another must bail-with-log UNIFORMLY rather than partially
// rewrite. Pre-fix the bare-array branch was folded immediately
// (no multi-return guard) while the RuleSet::from() branch logged
// "fold target ambiguous" and stayed flat — half-migrated across
// branches. Post-fix the multi-return guard runs ahead of either
// branch-specific path, so both branches stay flat together.
class MixedBranchReturnsRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->user()?->is_admin === true) {
            return [
                '*.foo' => FluentRule::string()->required(),
            ];
        }

        return [
            '*.bar' => FluentRule::integer()->min(0),
        ];
    }
}

?>
