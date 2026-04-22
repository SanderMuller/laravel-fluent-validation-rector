<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class SkipExistingMessagesMultiReturn extends Component
{
    // Codex regression pin: an existing messages() with multiple
    // top-level Return_ statements (early returns + a fallback) is
    // NOT mergeable — patching only the first return would leave
    // other branches without the migrated message. Preflight must
    // bail the whole conversion to keep #[Validate] intact.
    #[Validate('required|email', message: 'Your email is wrong.')]
    public string $email = '';

    public bool $localized = false;

    protected function messages(): array
    {
        if ($this->localized) {
            return ['email.required' => 'Localized.'];
        }

        return ['email.required' => 'Default.'];
    }
}

?>
