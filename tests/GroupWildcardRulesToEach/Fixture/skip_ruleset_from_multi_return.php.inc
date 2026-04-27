<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\RuleSet;

// 0.19.1 P0 skip-log fixture: rules() body has multiple top-level
// `Return_` statements (early-return / branched-return idiom).
// Per OQ#1 resolution (specs/0.19.1-…), 0.19.1 takes the conservative
// path — bail-with-log on multi-return; aggressive per-branch walk
// reopens on consumer signal. Real consumers (hihaho 44 rules(),
// mijntp 44 rules()) all single-Return_, so this case is a future-
// proofing skip-log without affecting current users.
class RuleSetFromMultiReturnRequest extends FormRequest
{
    public function rules(): RuleSet
    {
        if ($this->user()?->is_admin === true) {
            return RuleSet::from([
                '*.foo' => FluentRule::string()->required(),
            ]);
        }

        return RuleSet::from([
            '*.bar' => FluentRule::integer()->min(0),
        ]);
    }
}

?>
