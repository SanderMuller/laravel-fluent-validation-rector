<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// rules() was previously narrowed to `array<string, FluentRuleContract>`
// and now includes an allowlisted `Question::existsRule()`. The existing
// narrow annotation is stale — the array is mixed. Rector leaves the
// docblock unchanged (no rewrite), but emits a loud (default-mode) skip
// so the consumer notices the contract drift. Codex review must-have.

class SkipMixedWithAllowlistedStaleNarrowDocblock extends FormRequest
{
    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
            'question_id' => Question::existsRule(),
        ];
    }
}

?>
