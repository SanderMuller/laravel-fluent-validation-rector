<?php

namespace SanderMuller\FluentValidationRector\Tests\UpdateRulesReturnTypeDocblock\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// Pre-0.14.1 the rector emitted the FQN form
// `\SanderMuller\FluentValidation\Contracts\FluentRuleContract` directly
// in the @return annotation. 0.14.1 switched to the short
// `FluentRuleContract` name plus a queued `use` import. Files narrowed
// by older releases must still be recognized as already-narrowed and
// left alone — without the legacy FQN entry in `ALREADY_NARROWED_BODIES`,
// upgrading 0.13.x → 0.14.1 on a polished codebase would re-trigger
// the rector and emit a duplicate / conflicting annotation. Codex
// review (2026-04-26) catch on the 0.14.1 branch.
class SkipLegacyFqnAlreadyContract extends FormRequest
{
    /**
     * @return array<string, \SanderMuller\FluentValidation\Contracts\FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required(),
        ];
    }
}

?>
