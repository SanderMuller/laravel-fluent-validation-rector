<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use Livewire\Attributes\Validate;
use Livewire\Component;

class SkipNonLiteralMessageLogsLegacy extends Component
{
    public const string EMAIL_MSG = 'Your email is wrong.';

    // Non-literal `message:` value — class-const reference. Cannot be
    // statically migrated to a `messages()` array entry. Migration
    // signals "unsupported", the rules() conversion still runs, AND
    // the legacy skip-log fires for the unmigrated message: arg so
    // the user has a trail to fix it manually.
    #[Validate('required|email', message: self::EMAIL_MSG)]
    public string $email = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\FixtureMessageMigration;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SkipNonLiteralMessageLogsLegacy extends Component
{
    public const string EMAIL_MSG = 'Your email is wrong.';

    // Non-literal `message:` value — class-const reference. Cannot be
    // statically migrated to a `messages()` array entry. Migration
    // signals "unsupported", the rules() conversion still runs, AND
    // the legacy skip-log fires for the unmigrated message: arg so
    // the user has a trail to fix it manually.
    #[Validate]
    public string $email = '';

    /**
     * @return array<string, ValidationRule|string|array<mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
