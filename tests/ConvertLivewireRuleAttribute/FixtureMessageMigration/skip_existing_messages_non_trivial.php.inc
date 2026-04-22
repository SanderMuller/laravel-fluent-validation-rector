<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class SkipExistingMessagesNonTrivial extends Component
{
    /** @var array<string, string> */
    private array $cachedMessages = ['email.required' => 'Cached!'];

    #[Validate('required|email', message: 'Your email is wrong.')]
    public string $email = '';

    protected function messages(): array
    {
        // Non-trivial body — not a simple `return [...]`. Migration
        // can't merge into this safely. The preflight check bails the
        // whole conversion (rules() AND messages() both held back) so
        // the source `message:` data isn't lost. Skip-log records why.
        return $this->cachedMessages;
    }
}

?>
