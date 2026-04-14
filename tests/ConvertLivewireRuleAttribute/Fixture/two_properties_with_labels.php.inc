<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class Settings extends Component
{
    #[Rule('nullable|email', as: 'notificatie e-mailadres')]
    public ?string $notification_email = '';

    #[Rule('nullable|email', as: 'zakelijke notificatie e-mailadres')]
    public ?string $notification_business_email = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertLivewireRuleAttribute\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

final class Settings extends Component
{
    public ?string $notification_email = '';

    public ?string $notification_business_email = '';

    /**
     * @return array<string, FluentRule>
     */
    protected function rules(): array
    {
        return [
            'notification_email' => FluentRule::email()->nullable()->label('notificatie e-mailadres'),
            'notification_business_email' => FluentRule::email()->nullable()->label('zakelijke notificatie e-mailadres'),
        ];
    }
}

?>
