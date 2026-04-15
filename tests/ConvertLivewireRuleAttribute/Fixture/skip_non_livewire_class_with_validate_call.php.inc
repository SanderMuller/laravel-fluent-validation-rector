<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

/**
 * Regression pin for mijntp's 0.4.14 finding: classes outside Livewire
 * (Actions, FormRequests, Controllers, DataObjects) that happen to have a
 * `$this->validate([...])` call were producing spurious hybrid-bail skip
 * log entries because the candidacy gate was too weak — 34 such entries on
 * mijntp's production run, each a "hybrid-bail on non-candidate class"
 * false positive. The 0.4.17 gate bails silently on any class without a
 * `#[Rule]` / `#[Validate]` attribute, so this fixture's non-Livewire
 * class is a silent no-op with no skip-log noise.
 */
final class ResetUserPasswordAction
{
    public function handle(array $input): void
    {
        $this->validate($input, [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);
    }

    private function validate(array $data, array $rules): void
    {
        //
    }
}

?>
