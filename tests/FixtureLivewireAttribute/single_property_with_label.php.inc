<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use Livewire\Attributes\Rule;
use Livewire\Component;

final class InventorizationAdminSettings extends Component
{
    public object $company;

    #[Rule('nullable|email', as: 'notificatie e-mailadres')]
    public ?string $notification_email = '';
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureLivewireAttribute;

use SanderMuller\FluentValidation\FluentRule;
use Livewire\Attributes\Rule;
use Livewire\Component;

final class InventorizationAdminSettings extends Component
{
    public object $company;

    public ?string $notification_email = '';
    protected function rules(): array
    {
        return ['notification_email' => FluentRule::email()->nullable()->label('notificatie e-mailadres')];
    }
}

?>
