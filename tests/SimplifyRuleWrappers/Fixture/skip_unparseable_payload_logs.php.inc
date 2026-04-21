<?php

namespace SanderMuller\FluentValidationRector\Tests\SimplifyRuleWrappers\Fixture;

use Illuminate\Validation\Rules\Password;
use SanderMuller\FluentValidation\FluentRule;

class SkipUnparseablePayloadLogs
{
    public function rules(): array
    {
        // Known FluentRule chain (StringRule resolves cleanly), but the
        // payload is a non-v1-scope object/builder. Phase 6 codex review
        // surfaced this as a silent skip — must now emit a skip-log
        // entry pinned to the FluentRule context so users have a trail.
        return [
            'pwd' => FluentRule::string()->rule(Password::min(8)),
            'tag' => FluentRule::string()->rule('starts_with:tag_'),
        ];
    }
}

?>
